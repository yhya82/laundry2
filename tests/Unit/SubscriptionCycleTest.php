<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionCycleTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCycle(): SubscriptionCycle
    {
        $customer = Customer::factory()->subscription()->create();
        $package = SubscriptionPackage::factory()->create();

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => 2,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 40,
        ]);

        return SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => now(),
            'monthly_price_snapshot' => 300,
            'max_clothes_snapshot' => 40,
        ]);
    }

    public function test_is_exhausted_is_false_while_any_collection_is_still_scheduled(): void
    {
        $cycle = $this->makeCycle();
        $cycle->collections()->create(['subscription_id' => $cycle->subscription_id, 'status' => 'scheduled']);
        $cycle->collections()->create(['subscription_id' => $cycle->subscription_id, 'status' => 'collected']);

        $this->assertFalse($cycle->isExhausted());
    }

    public function test_is_exhausted_is_true_once_every_collection_is_resolved(): void
    {
        $cycle = $this->makeCycle();
        $cycle->collections()->create(['subscription_id' => $cycle->subscription_id, 'status' => 'collected']);
        $cycle->collections()->create(['subscription_id' => $cycle->subscription_id, 'status' => 'skipped']);

        $this->assertTrue($cycle->isExhausted(), 'No collections left in "scheduled" -- the cycle should read as exhausted.');
    }

    public function test_balance_due_and_is_paid(): void
    {
        $cycle = $this->makeCycle();

        $this->assertEquals(300.0, $cycle->balanceDue());
        $this->assertFalse($cycle->isPaid());

        $cycle->payments()->create([
            'subscription_id' => $cycle->subscription_id,
            'amount' => 300,
            'method' => 'cash',
        ]);
        $cycle = SubscriptionCycle::find($cycle->id); // fresh instance -- amountPaid() is memoized per-instance

        $this->assertEquals(0.0, $cycle->balanceDue());
        $this->assertTrue($cycle->isPaid());
    }
}
