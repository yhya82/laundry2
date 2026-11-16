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
