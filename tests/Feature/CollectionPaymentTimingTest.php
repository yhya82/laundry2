<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CollectionScheduler;
use App\Support\Numbering;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Record Collection's Pay now / Pay later choice (orders/show +
 * OrderController::advanceToCollection()).
 */
class CollectionPaymentTimingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin);
    }

    /** Walks an order through the only legal stages up to "completed". */
    protected function complete(Order $order): Order
    {
        foreach (['wash', 'completed'] as $status) {
            $order->status = $status;
            $order->save();
        }

        return $order->fresh();
    }

    /**
     * A completed subscription order on the first visit of a cycle with
     * $visits visits (so it's the last visit only when $visits === 1).
     */
    protected function subscriptionOrder(int $visits, float $overage = 0): Order
    {
        $customer = Customer::factory()->subscription()->create();
        $package = SubscriptionPackage::factory()->create(['monthly_price' => 500]);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => $visits,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 80,
        ]);
        CollectionScheduler::scheduleFirstCycle($subscription);

        $collection = $subscription->collections()->orderBy('id')->first();
        $collection->update(['status' => 'collected', 'collected_at' => now()]);

        return $this->complete(Order::create([
            'order_number' => Numbering::nextOrderNumber(),
            'customer_id' => $customer->id,
            'collection_id' => $collection->id,
            'order_source' => 'subscription',
            'subtotal' => 0,
            'cycle_overage_charge' => $overage,
        ]));
    }

    protected function walkInOrder(float $total): Order
    {
        return $this->complete(Order::create([
            'order_number' => Numbering::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => $total,
        ]));
    }

    protected function collect(Order $order, array $data = [])
    {
        return $this->from(route('orders.show', $order))
            ->post(route('orders.advance', $order), array_merge(['collected_by_type' => 'customer'], $data));
    }

    public function test_pay_later_on_a_non_final_visit_collects_without_recording_a_payment(): void
    {
        $order = $this->subscriptionOrder(visits: 2);

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasNoErrors();

        $this->assertSame('collection', $order->fresh()->status);
        $this->assertEquals(500, $order->subscriptionCycle()->balanceDue());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pay_later_ignores_any_amount_sent_with_it(): void
    {
        $order = $this->subscriptionOrder(visits: 2);

        $this->collect($order, ['payment_timing' => 'later', 'amount' => 200, 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame('collection', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pay_now_on_a_non_final_visit_records_the_payment(): void
    {
        $order = $this->subscriptionOrder(visits: 2);

        $this->collect($order, ['payment_timing' => 'now', 'amount' => 200, 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame('collection', $order->fresh()->status);
        $this->assertEquals(300, $order->subscriptionCycle()->balanceDue());
    }

    public function test_pay_now_without_an_amount_on_a_non_final_visit_asks_for_one(): void
    {
        $order = $this->subscriptionOrder(visits: 2);

        $this->collect($order, ['payment_timing' => 'now'])->assertSessionHasErrors('amount');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_pay_later_is_refused_on_the_last_visit_of_a_cycle(): void
    {
        $order = $this->subscriptionOrder(visits: 1);

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pay_later_is_refused_for_a_walk_in_balance(): void
    {
        $order = $this->walkInOrder(total: 150);

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_pay_later_is_refused_for_an_orders_own_extra_charge(): void
    {
        $order = $this->subscriptionOrder(visits: 2, overage: 40);

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_a_required_payment_still_has_to_be_in_full_with_pay_now(): void
    {
        $order = $this->walkInOrder(total: 150);

        $this->collect($order, ['payment_timing' => 'now', 'amount' => 100, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->collect($order, ['payment_timing' => 'now', 'amount' => 150, 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame('collection', $order->fresh()->status);
    }

    /** A completed order on the next unused visit of $first's cycle. */
    protected function nextVisitOrder(Order $first): Order
    {
        $collection = $first->collection->subscription->collections()->where('status', 'scheduled')->orderBy('id')->firstOrFail();
        $collection->update(['status' => 'collected', 'collected_at' => now()]);

        return $this->complete(Order::create([
            'order_number' => Numbering::nextOrderNumber(),
            'customer_id' => $first->customer_id,
            'collection_id' => $collection->id,
            'order_source' => 'subscription',
            'subtotal' => 0,
        ]));
    }

    // ---- Last collection of a cycle: order AND cycle ----------------------------

    public function test_the_last_visit_with_an_overage_charge_must_also_pay_the_cycle(): void
    {
        $order = $this->subscriptionOrder(visits: 1, overage: 40);

        // Only the 40 overage: refused -- the 500 cycle fee is still owed.
        $this->collect($order, ['payment_timing' => 'now', 'amount' => 40, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);

        // 540 covers both, split order first then cycle.
        $this->collect($order, ['payment_timing' => 'now', 'amount' => 540, 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $order = $order->fresh();
        $this->assertSame('collection', $order->status);
        $this->assertEquals(0, $order->balanceDue());
        $this->assertEquals(0, $order->subscriptionCycle()->balanceDue());
        $this->assertEquals(40, (float) $order->payments()->sum('amount'));
        $this->assertEquals(500, (float) $order->subscriptionCycle()->payments()->sum('amount'));
    }

    public function test_store_credit_is_split_order_first_then_cycle(): void
    {
        $order = $this->subscriptionOrder(visits: 1, overage: 40);
        $order->customer->creditTransactions()->create(['type' => 'credit', 'amount' => 100, 'balance_after' => 100]);

        $this->collect($order, ['payment_timing' => 'now', 'credit_applied' => 100, 'amount' => 440, 'method' => 'wave'])
            ->assertSessionHasNoErrors();

        $orderPayment = $order->payments()->firstOrFail();
        $cyclePayment = $order->fresh()->subscriptionCycle()->payments()->firstOrFail();

        $this->assertEquals(40, (float) $orderPayment->credit_applied, 'The order takes the credit first...');
        $this->assertSame('store_credit', $orderPayment->method);
        $this->assertEquals(60, (float) $cyclePayment->credit_applied, '...and the rest of the credit goes to the cycle.');
        $this->assertEquals(500, (float) $cyclePayment->amount);
        $this->assertSame('wave', $cyclePayment->method);
        $this->assertEquals(0, (float) $order->customer->fresh()->store_credit_balance);
    }

    public function test_more_store_credit_than_the_customer_has_is_refused(): void
    {
        $order = $this->subscriptionOrder(visits: 1);

        $this->collect($order, ['payment_timing' => 'now', 'credit_applied' => 500, 'amount' => 0])
            ->assertSessionHasErrors('credit_applied');

        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_a_non_final_visit_needs_only_its_own_charge_but_may_pay_toward_the_cycle(): void
    {
        $order = $this->subscriptionOrder(visits: 2, overage: 40);

        // 140: 40 to the order, the other 100 toward the cycle.
        $this->collect($order, ['payment_timing' => 'now', 'amount' => 140, 'method' => 'cash'])
            ->assertSessionHasNoErrors();

        $order = $order->fresh();
        $this->assertSame('collection', $order->status);
        $this->assertEquals(0, $order->balanceDue());
        $this->assertEquals(400, $order->subscriptionCycle()->balanceDue());
    }

    public function test_paying_more_than_the_order_and_cycle_owe_is_refused(): void
    {
        $order = $this->subscriptionOrder(visits: 2, overage: 40);

        $this->collect($order, ['payment_timing' => 'now', 'amount' => 600, 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_with_two_orders_waiting_only_the_second_pickup_is_the_last(): void
    {
        $first = $this->subscriptionOrder(visits: 2);
        $second = $this->nextVisitOrder($first);

        $this->collect($first, ['payment_timing' => 'later'])->assertSessionHasNoErrors();
        $this->assertSame('collection', $first->fresh()->status);

        $this->collect($second, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');
        $this->assertSame('completed', $second->fresh()->status);
    }

    public function test_cancelled_remaining_visits_make_this_the_last_collection(): void
    {
        $order = $this->subscriptionOrder(visits: 3);
        $order->collection->subscription->collections()->where('status', 'scheduled')
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');
    }

    public function test_a_cancelled_subscription_makes_this_the_last_collection(): void
    {
        $order = $this->subscriptionOrder(visits: 3);
        $this->post(route('subscriptions.cancel', $order->collection->subscription));

        $this->collect($order, ['payment_timing' => 'later'])->assertSessionHasErrors('payment_timing');
    }

    public function test_the_panel_shows_both_amounts_on_the_last_visit(): void
    {
        $order = $this->subscriptionOrder(visits: 1, overage: 40);

        $this->get(route('orders.show', $order))
            ->assertOk()
            ->assertSeeInOrder(['Order extra charge', 'GMD 40.00', 'Subscription cycle balance', 'GMD 500.00', 'Due to collect', 'GMD 540.00'])
            ->assertSee('One payment is split automatically: the order first, then the cycle.')
            ->assertSee('Last collection of this cycle, so everything still owed must be paid in full.');
    }

    public function test_the_panel_greys_out_pay_later_only_when_payment_is_required(): void
    {
        $payLaterDisabled = fn (string $html) => (bool) preg_match('/value="later"[^>]*\sdisabled/', $html);

        $final = $this->subscriptionOrder(visits: 1);
        $html = $this->get(route('orders.show', $final))->assertOk()->assertSee('Last collection of this cycle')->getContent();
        $this->assertTrue($payLaterDisabled($html));

        $walkIn = $this->walkInOrder(total: 150);
        $html = $this->get(route('orders.show', $walkIn))->assertOk()->assertSee('Must be paid in full to collect.')->getContent();
        $this->assertTrue($payLaterDisabled($html));

        $nonFinal = $this->subscriptionOrder(visits: 2);
        $html = $this->get(route('orders.show', $nonFinal))->assertOk()->assertSee('Pay later')->getContent();
        $this->assertFalse($payLaterDisabled($html));
    }
}
