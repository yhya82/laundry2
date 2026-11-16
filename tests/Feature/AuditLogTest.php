<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\LoginEvent;
use App\Models\Order;
use App\Models\User;
use App\Support\Numbering;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * The Audit Log: before → after trigger rows, the newly audited tables, the
 * merged security timeline (Admins only), filters and the CSV export.
 */
class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create(['name' => 'Ada Admin']);
        $this->admin->syncRoles(['Admin']);
    }

    protected function order(): Order
    {
        return Order::create([
            'order_number' => Numbering::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 150,
        ]);
    }

    protected function auditor(): User
    {
        Role::create(['name' => 'Auditor', 'guard_name' => 'web'])->givePermissionTo('audit.view');
        $auditor = User::factory()->create();
        $auditor->syncRoles(['Auditor']);

        return $auditor;
    }

    protected function lastLog(string $description): ActivityLog
    {
        return ActivityLog::where('description', $description)->latest('id')->firstOrFail();
    }

    // ---- Triggers ------------------------------------------------------------

    public function test_updates_record_before_and_after_and_untracked_changes_are_skipped(): void
    {
        $order = $this->order();
        $order->status = 'wash';
        $order->save();

        $props = $this->lastLog('updated orders')->properties;
        $this->assertSame('received', $props['old']['status']);
        $this->assertSame('wash', $props['new']['status']);
        $this->assertSame($order->order_number, $props['new']['order_number']);

        $before = ActivityLog::count();
        $order->update(['notes' => 'Just a note']); // not a tracked column
        $this->assertSame($before, ActivityLog::count(), 'An update that changes nothing tracked is not logged.');
    }

    public function test_user_changes_are_audited_without_ever_storing_the_password(): void
    {
        $user = User::factory()->create(['name' => 'Fatou']);
        $user->update(['password' => 'a-brand-new-password']);

        $log = $this->lastLog('updated users');
        $this->assertTrue((bool) $log->properties['new']['password_changed']);
        $this->assertStringNotContainsString($user->fresh()->password, json_encode($log->properties));

        $user->forceFill(['is_active' => false])->save();
        $props = $this->lastLog('updated users')->properties;
        $this->assertEquals(1, $props['old']['is_active']);
        $this->assertEquals(0, $props['new']['is_active']);
    }

    public function test_role_and_permission_grants_and_removals_are_audited(): void
    {
        $user = User::factory()->create();
        $user->syncRoles(['Laundry']);
        $user->syncRoles([]);

        $this->assertSame('Laundry', $this->lastLog('role granted')->properties['role']);
        $this->assertSame('Laundry', $this->lastLog('role removed')->properties['role']);

        $role = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $role->givePermissionTo('payments.view');
        $role->revokePermissionTo('payments.view');

        $this->assertSame('payments.view', $this->lastLog('permission granted')->properties['permission']);
        $this->assertSame('Cashier', $this->lastLog('permission removed')->properties['role']);
    }

    public function test_catalog_changes_and_deletions_are_audited(): void
    {
        $package = LaundryPackage::factory()->create(['name' => 'Wash & Fold', 'base_price' => 100]);
        $package->update(['base_price' => 120]);

        $props = $this->lastLog('updated laundry_packages')->properties;
        $this->assertEquals(100, $props['old']['base_price']);
        $this->assertEquals(120, $props['new']['base_price']);

        $package->delete();
        $this->assertSame('Wash & Fold', $this->lastLog('deleted laundry_packages')->properties['old']['name']);
    }

    // ---- Page ----------------------------------------------------------------

    public function test_entries_read_as_sentences_with_before_and_after(): void
    {
        $order = $this->order();
        $order->status = 'wash';
        $order->save();

        $this->actingAs($this->admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('Updated order '.$order->order_number.': Received → Wash')
            ->assertSee('Created order '.$order->order_number);
    }

    public function test_admins_see_security_events_in_the_same_timeline(): void
    {
        $this->post('/login', ['email' => 'intruder@example.com', 'password' => 'guess']);
        $this->order();

        $this->actingAs($this->admin)->get(route('audit.index'))
            ->assertSee('Failed login for intruder@example.com')
            ->assertSee('Created order')
            ->assertSee('Security');

        $this->get(route('audit.index', ['tab' => 'security']))
            ->assertSee('Failed login for intruder@example.com')
            ->assertDontSee('Created order');

        $this->get(route('audit.index', ['tab' => 'data']))
            ->assertDontSee('Failed login for intruder@example.com')
            ->assertSee('Created order');
    }

    public function test_non_admins_with_audit_access_never_see_security_events(): void
    {
        $this->post('/login', ['email' => 'intruder@example.com', 'password' => 'guess']);
        $this->order();
        $auditor = $this->auditor();

        foreach (['all', 'security', 'data'] as $tab) {
            $this->actingAs($auditor)->get(route('audit.index', ['tab' => $tab, 'action' => 'failed']))
                ->assertOk()
                ->assertDontSee('intruder@example.com');
        }

        $this->get(route('audit.index'))->assertSee('Created order')->assertDontSee('Open in Security Center');

        $csv = $this->get(route('audit.export', ['tab' => 'security']))->streamedContent();
        $this->assertStringNotContainsString('intruder@example.com', $csv);
    }

    public function test_filters_narrow_the_timeline(): void
    {
        $order = $this->order();
        LaundryPackage::factory()->create(['name' => 'Duvet Clean']);
        $this->post('/login', ['email' => 'intruder@example.com', 'password' => 'guess']);

        $this->actingAs($this->admin);

        $this->get(route('audit.index', ['module' => 'Orders']))
            ->assertSee('Created order '.$order->order_number)
            ->assertDontSee('Duvet Clean')
            ->assertDontSee('intruder@example.com');

        $this->get(route('audit.index', ['q' => $order->order_number]))
            ->assertSee($order->order_number)
            ->assertDontSee('Duvet Clean');

        $this->get(route('audit.index', ['action' => 'failed']))
            ->assertSee('intruder@example.com')
            ->assertDontSee('Created order');
    }

    public function test_older_after_only_rows_still_display(): void
    {
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'updated orders', 'subject_type' => 'App\Models\Order', 'subject_id' => 999,
            'causer_type' => 'App\Models\User', 'causer_id' => $this->admin->id,
            'properties' => json_encode(['status' => 'completed', 'total_amount' => '150.00']), 'created_at' => now(),
        ]);

        $this->actingAs($this->admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Updated order #999');
    }

    public function test_export_downloads_the_filtered_timeline_as_csv(): void
    {
        $order = $this->order();
        $this->post('/login', ['email' => 'intruder@example.com', 'password' => 'guess']);

        $response = $this->actingAs($this->admin)->get(route('audit.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();
        $this->assertStringStartsWith('Time,Source,Module,Action', $csv);
        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringContainsString('intruder@example.com', $csv);

        $ordersOnly = $this->get(route('audit.export', ['module' => 'Orders']))->streamedContent();
        $this->assertStringNotContainsString('intruder@example.com', $ordersOnly);
    }

    public function test_the_audit_log_needs_audit_view(): void
    {
        $staff = User::factory()->create();
        $staff->syncRoles(['Laundry']);

        $this->actingAs($staff)->get(route('audit.index'))->assertForbidden();
        $this->get(route('audit.export'))->assertForbidden();
    }
}
