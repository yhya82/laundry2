<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CollectionScheduler;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CollectionsTest extends TestCase
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
    }

    protected function makeSubscription(int $collectionsPerMonth = 1, string $collectionType = 'non_scheduled'): Subscription
    {
        $customer = Customer::factory()->subscription()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => $collectionsPerMonth,
            'collection_type' => $collectionType,
            'max_clothes_per_cycle' => 80,
        ]);

        $cycle = SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => now(),
            'monthly_price_snapshot' => (float) $package->monthly_price,
            'max_clothes_snapshot' => 80,
        ]);

        CollectionScheduler::generateCollections($subscription, $cycle, $collectionType, now(), $collectionsPerMonth);

        return $subscription;
    }

    public function test_a_scheduled_collection_can_be_plain_cancelled(): void
    {
        $subscription = $this->makeSubscription();
        $collection = $subscription->collections()->first();

        $response = $this->post(route('collections.cancel', $collection), ['reason' => 'Customer travelling']);
        $response->assertSessionDoesntHaveErrors();

        $collection->refresh();
        $this->assertSame('cancelled', $collection->status);
        $this->assertSame('Customer travelling', $collection->cancellation_reason);
        $this->assertNull($collection->combined_into_collection_id);
    }

    public function test_cancelling_the_only_collection_in_a_cycle_closes_it(): void
    {
        $subscription = $this->makeSubscription();
        $collection = $subscription->collections()->first();
        $cycle = SubscriptionCycle::find($collection->subscription_cycle_id);
        $this->assertNull($cycle->ends_on);

        $this->post(route('collections.cancel', $collection), ['reason' => 'x']);

        $cycle->refresh();
        $this->assertNotNull($cycle->ends_on, 'The cycle should have been backfilled with an end date once its only collection resolved.');
    }

    public function test_a_collection_can_be_skipped_and_combined_into_another(): void
    {
        $subscription = $this->makeSubscription(collectionsPerMonth: 2);
        [$first, $second] = $subscription->collections()->orderBy('id')->get();

        $response = $this->post(route('collections.cancel', $first), [
            'reason' => 'Combining into next pickup',
            'combined_into_collection_id' => $second->id,
        ]);
        $response->assertSessionDoesntHaveErrors();

        $first->refresh();
        $this->assertSame('cancelled', $first->status);
        $this->assertSame($second->id, $first->combined_into_collection_id);

        // The cycle isn't exhausted -- $second is still scheduled.
        $cycle = SubscriptionCycle::find($first->subscription_cycle_id);
        $this->assertFalse($cycle->isExhausted());
    }

    public function test_combining_into_a_collection_on_a_different_subscription_is_rejected(): void
    {
        $subscription = $this->makeSubscription();
        $collection = $subscription->collections()->first();

        $otherSubscription = $this->makeSubscription();
        $foreignCollection = $otherSubscription->collections()->first();

        $response = $this->post(route('collections.cancel', $collection), [
            'combined_into_collection_id' => $foreignCollection->id,
        ]);
        $response->assertSessionHasErrors('combined_into_collection_id');

        $collection->refresh();
        $this->assertSame('scheduled', $collection->status, 'Rejected combine target should not have changed anything.');
    }

    public function test_an_already_resolved_collection_cannot_be_cancelled_again(): void
    {
        $subscription = $this->makeSubscription();
        $collection = $subscription->collections()->first();
        $collection->update(['status' => 'collected', 'collected_at' => now()]);

        $response = $this->post(route('collections.cancel', $collection), ['reason' => 'x']);
        $response->assertSessionHasErrors('collection');

        $collection->refresh();
        $this->assertSame('collected', $collection->status);
    }

    public function test_a_collection_on_a_paused_subscription_cannot_be_cancelled_or_collected(): void
    {
        $subscription = $this->makeSubscription();
        $subscription->update(['status' => 'paused']);
        $collection = $subscription->collections()->first();

        $response = $this->post(route('collections.cancel', $collection), ['reason' => 'x']);
        $response->assertSessionHasErrors('collection');

        $response = $this->get(route('collections.collect', $collection));
        $response->assertRedirect(route('collections.index'));
        $response->assertSessionHasErrors('collection');
    }

    public function test_scheduled_type_collection_dates_never_collide_on_the_same_subscription(): void
    {
        $customer = Customer::factory()->subscription()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => 1,
            'collection_type' => 'scheduled',
            'max_clothes_per_cycle' => 80,
        ]);

        $startsOn = now();

        $cycle1 = SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => $startsOn,
            'monthly_price_snapshot' => 300,
            'max_clothes_snapshot' => 80,
        ]);
        CollectionScheduler::generateCollections($subscription, $cycle1, 'scheduled', $startsOn, 1);

        // A second cycle (subscription_cycles has its own unique
        // (subscription_id, starts_on) index, so this one starts a day
        // later) generated with the SAME collection start date passed to
        // generateCollections() -- the naive collection date would collide
        // with the first cycle's (collections has a unique
        // (subscription_id, scheduled_date) index covering the
        // subscription's whole history, not just one cycle).
        $cycle2 = SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => $startsOn->copy()->addDay(),
            'monthly_price_snapshot' => 300,
            'max_clothes_snapshot' => 80,
        ]);
        CollectionScheduler::generateCollections($subscription, $cycle2, 'scheduled', $startsOn, 1);

        $dates = $subscription->collections()->pluck('scheduled_date')->map(fn ($d) => $d->toDateString());

        $this->assertCount(2, $dates);
        $this->assertSame($dates->unique()->count(), $dates->count(), 'No two collections on the same subscription should share a scheduled_date.');
    }
}
