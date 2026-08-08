<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DamageRecord;
use App\Models\DamageType;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DamageAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
    }

    protected function makeOrder(): Order
    {
        $order = Order::create([
            'order_number' => 'DA-'.uniqid(),
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);
        $order->refresh();

        return $order;
    }

    public function test_reporting_damage_records_who_reported_it(): void
    {
        $reporter = User::factory()->create();
        $reporter->syncRoles(['Admin']);
        $this->actingAs($reporter->fresh());

        $order = $this->makeOrder();
        $damageType = DamageType::factory()->create();

        $this->post(route('damage.store', $order), [
            'damage_type_id' => $damageType->id,
            'item_description' => 'Shirt',
            'description' => 'Torn sleeve',
        ])->assertRedirect();

        $damageRecord = DamageRecord::first();
        $this->assertSame($reporter->id, $damageRecord->reported_by);
        $this->assertSame($reporter->id, $damageRecord->reportedBy->id);
    }

    public function test_a_review_chain_transition_is_logged_with_who_made_it(): void
    {
        $reporter = User::factory()->create();
        $reporter->syncRoles(['Admin']);

        $reviewer = User::factory()->create();
        $reviewer->syncRoles(['Admin']);

        $order = $this->makeOrder();
        $damageRecord = $order->damageRecords()->create([
            'damage_type_id' => DamageType::factory()->create()->id,
            'reported_by' => $reporter->id,
            'item_description' => 'Shirt',
        ]);

        $this->actingAs($reviewer->fresh())
            ->post(route('damage.transition', $damageRecord), ['status' => 'under_investigation'])
            ->assertRedirect();

        $damageRecord = DamageRecord::find($damageRecord->id);
        $this->assertSame('under_investigation', $damageRecord->status);

        $entry = $damageRecord->statusHistory()->latest('id')->first();
        $this->assertNotNull($entry, 'The transition should have written a damage_status_history row.');
        $this->assertSame('pending_review', $entry->from_status);
        $this->assertSame('under_investigation', $entry->to_status);
        $this->assertSame($reviewer->id, $entry->changed_by);
    }

    public function test_resolving_a_damage_record_logs_the_resolver_in_the_same_history_trail(): void
    {
        $resolver = User::factory()->create();
        $resolver->syncRoles(['Admin']);

        $order = $this->makeOrder();
        $damageRecord = $order->damageRecords()->create([
            'damage_type_id' => DamageType::factory()->create()->id,
            'item_description' => 'Shirt',
        ]);
        $damageRecord->status = 'approved';
        $damageRecord->save();

        $this->actingAs($resolver->fresh())
            ->post(route('damage.resolve', $damageRecord), [
                'resolution_type' => 'cash',
                'amount' => 25,
            ])->assertRedirect();

        $damageRecord = DamageRecord::find($damageRecord->id);
        $this->assertSame('resolved', $damageRecord->status);
        $this->assertSame($resolver->id, $damageRecord->resolution->resolved_by);

        // The resolve()-triggered status flip (approved -> resolved) should
        // land in the same history trail as a manual transition() call would,
        // since both go through the same AFTER UPDATE trigger. Ordered by id,
        // not created_at -- the pending_review->approved seed change above and
        // this flip can land in the same DATETIME second.
        $entry = $damageRecord->statusHistory()->latest('id')->first();
        $this->assertSame('approved', $entry->from_status);
        $this->assertSame('resolved', $entry->to_status);
        $this->assertSame($resolver->id, $entry->changed_by);
    }

    public function test_the_damage_report_page_displays_reporter_and_review_history(): void
    {
        $reporter = User::factory()->create(['name' => 'Reporter Rita']);
        $reporter->syncRoles(['Admin']);

        $reviewer = User::factory()->create(['name' => 'Reviewer Rex']);
        $reviewer->syncRoles(['Admin']);

        $order = $this->makeOrder();
        $damageRecord = $order->damageRecords()->create([
            'damage_type_id' => DamageType::factory()->create()->id,
            'reported_by' => $reporter->id,
            'item_description' => 'Shirt',
        ]);

        $this->actingAs($reviewer->fresh())
            ->post(route('damage.transition', $damageRecord), ['status' => 'under_investigation'])
            ->assertRedirect();

        $response = $this->actingAs($reviewer->fresh())->get(route('damage.show', $damageRecord));

        $response->assertOk();
        $response->assertSee('Review History');
        $response->assertSee('Reporter Rita');
        $response->assertSee('Reviewer Rex');
    }
}
