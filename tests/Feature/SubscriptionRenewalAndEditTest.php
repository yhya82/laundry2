<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CollectionScheduler;
use Carbon\Carbon;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SubscriptionRenewalAndEditTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['Admin']);
        $this->actingAs($this->admin);

        Carbon::setTestNow('2026-11-16 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function makeSubscription(string $type = 'non_scheduled', int $collections = 1, float $price = 500): Subscription
    {
        $customer = Customer::factory()->subscription()->create();
        $package = SubscriptionPackage::factory()->create(['monthly_price' => $price]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'collections_per_month' => $collections,
            'collection_type' => $type,
            'max_clothes_per_cycle' => 80,
        ]);

        CollectionScheduler::scheduleFirstCycle($subscription);

        return $subscription->fresh();
    }

    protected function payCycle(SubscriptionCycle $cycle, ?float $amount = null): Payment
    {
        return Payment::create([
            'subscription_id' => $cycle->subscription_id,
            'subscription_cycle_id' => $cycle->id,
            'amount' => $amount ?? (float) $cycle->monthly_price_snapshot,
            'method' => 'cash',
            'received_by' => $this->admin->id,
        ]);
    }

    /** Collect every open visit in the current cycle and pay the cycle, so it's renewable. */
    protected function exhaustAndPay(Subscription $subscription): SubscriptionCycle
    {
        $cycle = $subscription->currentCycle();
        $cycle->collections()->update(['status' => 'collected', 'collected_at' => now()]);
        $this->payCycle($cycle);

        return $cycle;
    }

    protected function renewPayload(Subscription $subscription, array $overrides = []): array
    {
        return array_merge([
            'subscription_package_id' => $subscription->subscription_package_id,
            'collection_type' => $subscription->collection_type,
            'start_date' => now()->toDateString(),
            'collections_per_month' => $subscription->collections_per_month,
            'max_clothes_per_cycle' => $subscription->max_clothes_per_cycle,
        ], $overrides);
    }

    protected function editPayload(Subscription $subscription, array $overrides = []): array
    {
        return array_merge([
            'subscription_package_id' => (string) $subscription->subscription_package_id,
            'collection_type' => $subscription->collection_type,
            'collections_per_month' => (string) $subscription->collections_per_month,
            'max_clothes_per_cycle' => (string) $subscription->max_clothes_per_cycle,
        ], $overrides);
    }

    // ---- Renewal --------------------------------------------------------

    public function test_an_exhausted_cycle_can_be_renewed_on_the_same_day(): void
    {
        $subscription = $this->makeSubscription();
        $oldCycle = $this->exhaustAndPay($subscription);

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, $subscription->cycles()->count());

        $newCycle = $subscription->fresh()->currentCycle();
        $this->assertNotSame($oldCycle->id, $newCycle->id);
        $this->assertSame(now()->toDateString(), $newCycle->starts_on->toDateString());
        $this->assertSame($oldCycle->starts_on->toDateString(), $newCycle->starts_on->toDateString());
        $this->assertSame(1, $newCycle->collections()->where('status', 'scheduled')->count());
    }

    public function test_a_renewal_always_starts_today_even_if_another_date_is_sent(): void
    {
        $subscription = $this->makeSubscription('scheduled', 2);
        $this->exhaustAndPay($subscription);

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription, [
            'start_date' => '2027-01-10',
        ]))->assertSessionDoesntHaveErrors();

        $newCycle = $subscription->fresh()->currentCycle();
        $this->assertSame(now()->toDateString(), $newCycle->starts_on->toDateString());

        // The old cycle's first visit already holds today's date, so the
        // unique (subscription_id, scheduled_date) key nudges this cycle's
        // first visit to tomorrow -- but never out to the submitted date.
        $this->assertSame(
            now()->addDay()->toDateString(),
            Carbon::parse($newCycle->collections()->min('scheduled_date'))->toDateString()
        );
        $this->assertTrue(Carbon::parse($newCycle->collections()->max('scheduled_date'))->lt(now()->addMonthNoOverflow()));
    }

    public function test_renewal_is_refused_while_the_customer_has_an_outstanding_balance(): void
    {
        $subscription = $this->makeSubscription();
        // Visits used up, but the cycle fee was never paid.
        $subscription->currentCycle()->collections()->update(['status' => 'collected', 'collected_at' => now()]);

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription))
            ->assertRedirect(route('customers.show', $subscription->customer_id))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(1, $subscription->cycles()->count(), 'No cycle may be created while money is owed.');
    }

    public function test_renew_is_not_offered_while_the_customer_owes_money(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->currentCycle()->collections()->update(['status' => 'collected', 'collected_at' => now()]);

        $this->get(route('subscriptions.show', $subscription))
            ->assertOk()
            ->assertSee('renewal unavailable')
            ->assertDontSee('renew-cycle-'.$subscription->id, false);

        $this->get(route('customers.show', $subscription->customer_id))
            ->assertOk()
            ->assertSee('renewal unavailable')
            ->assertDontSee("'renew-cycle-{$subscription->id}'", false);

        Livewire::test('laundry-terminal', ['customerId' => $subscription->customer_id])
            ->assertSee('renewal unavailable')
            ->assertDontSee('renew-cycle-choice-'.$subscription->id, false);

        // Once the cycle is paid, Renew is offered again.
        $this->payCycle($subscription->currentCycle());

        $this->get(route('subscriptions.show', $subscription))
            ->assertDontSee('renewal unavailable')
            ->assertSee('renew-cycle-'.$subscription->id, false);
    }

    public function test_renewal_is_refused_while_the_current_cycle_has_open_visits(): void
    {
        $subscription = $this->makeSubscription(collections: 2);
        $this->payCycle($subscription->currentCycle());

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(1, $subscription->cycles()->count());
    }

    public function test_submitting_renew_twice_only_creates_one_cycle(): void
    {
        $subscription = $this->makeSubscription();
        $this->exhaustAndPay($subscription);

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription))
            ->assertSessionDoesntHaveErrors();
        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription))
            ->assertSessionHasErrors('subscription');

        $this->assertSame(2, $subscription->cycles()->count());
    }

    public function test_a_scheduled_end_date_is_the_last_collection_date_and_never_stacks(): void
    {
        $subscription = $this->makeSubscription('scheduled', 4);

        $lastDate = $subscription->currentCycle()->collections()->max('scheduled_date');
        $this->assertSame(Carbon::parse($lastDate)->toDateString(), $subscription->end_date->toDateString());
        $this->assertTrue($subscription->end_date->lt(now()->addMonthNoOverflow()), 'end_date must not be padded out to a full month.');

        // Renew a few days later, while the old end_date is still in the future.
        $this->exhaustAndPay($subscription);
        Carbon::setTestNow('2026-11-20 10:00:00');

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription->fresh()))
            ->assertSessionDoesntHaveErrors();

        $subscription->refresh();
        $newCycle = $subscription->currentCycle();

        $this->assertSame($newCycle->ends_on->toDateString(), $subscription->end_date->toDateString());
        $this->assertSame(
            Carbon::parse($newCycle->collections()->max('scheduled_date'))->toDateString(),
            $subscription->end_date->toDateString()
        );
    }

    public function test_a_non_scheduled_renewal_leaves_end_date_untouched(): void
    {
        $subscription = $this->makeSubscription('non_scheduled', 2);
        $subscription->update(['end_date' => '2026-12-31']);
        $this->exhaustAndPay($subscription);

        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription->fresh()))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-12-31', $subscription->fresh()->end_date->toDateString());
    }

    public function test_a_new_non_scheduled_subscription_has_no_end_date(): void
    {
        $this->assertNull($this->makeSubscription('non_scheduled', 3)->end_date);
    }

    // ---- Edit -----------------------------------------------------------

    public function test_a_package_only_edit_keeps_the_existing_collections(): void
    {
        $subscription = $this->makeSubscription(collections: 2);
        $cancelled = $subscription->collections()->orderBy('id')->first();
        $cancelled->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'Travelling']);
        $idsBefore = $subscription->collections()->orderBy('id')->pluck('id')->all();

        $newPackage = SubscriptionPackage::factory()->create(['monthly_price' => 700]);

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription, [
            'subscription_package_id' => (string) $newPackage->id,
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame($idsBefore, $subscription->collections()->orderBy('id')->pluck('id')->all());
        $this->assertSame('Travelling', $cancelled->fresh()->cancellation_reason);
        $this->assertEquals(700, (float) $subscription->currentCycle()->monthly_price_snapshot);
    }

    public function test_changing_the_number_of_collections_rebuilds_the_cycle(): void
    {
        $subscription = $this->makeSubscription(collections: 1);

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription, [
            'collections_per_month' => '3',
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(3, $subscription->currentCycle()->collections()->count());
        $this->assertSame(1, $subscription->cycles()->count(), 'The cycle row is updated in place, never recreated.');
    }

    public function test_switching_to_non_scheduled_keeps_the_end_date_in_the_database(): void
    {
        $subscription = $this->makeSubscription('scheduled', 2);
        $endDate = $subscription->end_date->toDateString();

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription, [
            'collection_type' => 'non_scheduled',
        ]))->assertSessionDoesntHaveErrors();

        $subscription->refresh();
        $this->assertSame('non_scheduled', $subscription->collection_type);
        $this->assertSame($endDate, $subscription->end_date->toDateString());
        $this->assertSame(0, $subscription->currentCycle()->collections()->whereNotNull('scheduled_date')->count());
    }

    public function test_edit_is_blocked_after_a_collected_visit_in_any_earlier_cycle(): void
    {
        $subscription = $this->makeSubscription();
        $this->exhaustAndPay($subscription);
        $this->post(route('subscriptions.renew', $subscription), $this->renewPayload($subscription));

        // The new cycle has nothing collected, but the subscription does.
        $this->assertSame(0, $subscription->currentCycle()->collections()->where('status', 'collected')->count());

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription->fresh(), [
            'collections_per_month' => '3',
        ]))->assertSessionHasErrors('subscription');

        $this->assertSame(1, $subscription->fresh()->collections_per_month);
    }

    public function test_a_paused_subscription_cannot_be_edited_until_resumed(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->update(['status' => 'paused']);

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription, [
            'collections_per_month' => '2',
        ]))->assertSessionHasErrors('subscription');

        $this->post(route('subscriptions.resume', $subscription))->assertSessionDoesntHaveErrors();

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription->fresh(), [
            'collections_per_month' => '2',
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame(2, $subscription->fresh()->collections_per_month);
    }

    public function test_a_cancelled_subscription_cannot_be_edited(): void
    {
        $subscription = $this->makeSubscription(collections: 2);
        $this->post(route('subscriptions.cancel', $subscription));

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription->fresh(), [
            'collections_per_month' => '3',
        ]))->assertSessionHasErrors('subscription');

        $this->assertSame(0, $subscription->collections()->where('status', 'scheduled')->count(), 'No collections may be brought back.');
    }

    public function test_a_cheaper_package_on_a_paid_cycle_moves_the_excess_to_store_credit(): void
    {
        $subscription = $this->makeSubscription(price: 500);
        $this->payCycle($subscription->currentCycle());
        $cheaper = SubscriptionPackage::factory()->create(['monthly_price' => 300]);

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription, [
            'subscription_package_id' => (string) $cheaper->id,
        ]))->assertSessionDoesntHaveErrors();

        $cycle = $subscription->currentCycle();
        $this->assertEquals(300, $cycle->amountPaid());
        $this->assertEquals(0, $cycle->balanceDue());
        $this->assertEquals(200, (float) $subscription->customer->fresh()->store_credit_balance);

        // Switching up again afterwards: only the 300 still on the cycle
        // counts, and the customer owes the difference.
        $pricier = SubscriptionPackage::factory()->create(['monthly_price' => 800]);

        $this->put(route('subscriptions.update', $subscription), $this->editPayload($subscription->fresh(), [
            'subscription_package_id' => (string) $pricier->id,
        ]))->assertSessionDoesntHaveErrors();

        $cycle = $subscription->currentCycle();
        $this->assertEquals(500, $cycle->balanceDue());
        $this->assertEquals(200, (float) $subscription->customer->fresh()->store_credit_balance, 'No second credit.');
    }

    // ---- Cancel / Terminal ------------------------------------------------

    public function test_cancelling_a_non_scheduled_subscription_closes_its_cycle(): void
    {
        $subscription = $this->makeSubscription('non_scheduled', 2);
        $this->assertNull($subscription->currentCycle()->ends_on);

        $this->post(route('subscriptions.cancel', $subscription));

        $this->assertNotNull($subscription->currentCycle()->ends_on);
    }

    public function test_the_terminal_only_picks_a_collection_from_the_current_cycle(): void
    {
        $subscription = $this->makeSubscription('non_scheduled', 1);
        $oldCycle = $subscription->currentCycle();

        // An older cycle left with an open visit (e.g. created before this
        // fix), plus a newer current cycle.
        $newCycle = CollectionScheduler::scheduleCycle($subscription, now());
        $this->assertSame(1, $oldCycle->collections()->where('status', 'scheduled')->count());

        $component = Livewire::test('laundry-terminal', ['customerId' => $subscription->customer_id]);

        $pickedCycleId = \App\Models\Collection::find($component->get('collectionId'))->subscription_cycle_id;
        $this->assertSame($newCycle->id, $pickedCycleId);
    }
}
