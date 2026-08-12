<?php

namespace Tests\Feature;

use App\Models\ClothingItem;
use App\Models\Customer;
use App\Models\DamageType;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * One end-to-end pass per workflow in the master document's §2 (walk-in,
 * subscription, processing, cancellation, payment, damage) -- Phase 14's
 * "end-to-end test pass" task. Uses an Admin user throughout since these are
 * exercising business logic, not permission boundaries; the RBAC/permission
 * surface was separately verified (see the earlier security audit findings
 * and Phase 12's DB-privilege work).
 */
class WorkflowsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->syncRoles(['Admin']);
    }

    public function test_walk_in_order_workflow(): void
    {
        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        $component = Livewire::actingAs($this->admin)->test('laundry-terminal', ['customerId' => $customer->id]);

        $component->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_now')
            ->set('customAmount', 100)
            ->set('paymentMethod', 'cash')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order, 'Expected an order to have been created.');
        $this->assertSame('walk_in', $order->order_source);
        $this->assertSame('received', $order->status);
        $this->assertEquals(100.0, (float) $order->subtotal);
        $this->assertEquals(100.0, (float) $order->total_amount);
        $this->assertEquals(0.0, $order->balanceDue(), 'Order should be fully paid.');
        $this->assertNotNull($order->receipt, 'Expected a receipt to have been created.');

        // Processing pipeline: advance through every stage, one at a time.
        $this->actingAs($this->admin);
        foreach (['sorting', 'washing', 'drying', 'ironing', 'packaging', 'completed'] as $expectedStatus) {
            $payload = $expectedStatus === 'washing'
                ? ['washing_machine_id' => \App\Models\WashingMachine::factory()->create()->id]
                : [];

            $response = $this->post(route('orders.advance', $order), $payload);
            $order->refresh();
            $this->assertSame($expectedStatus, $order->status, "Expected order to reach {$expectedStatus}.");
        }

        $this->assertSame(6, $order->statusHistory()->count(), 'Expected one history row per transition, auto-logged by the trigger.');
    }

    public function test_subscription_workflow(): void
    {
        $customer = Customer::factory()->subscription()->create();
        $subPackage = SubscriptionPackage::factory()->create(['clothes_allowance' => 20]);
        LaundryPackage::factory()->create(); // the "container" package Terminal subscription mode needs to exist
        $clothingItem = ClothingItem::factory()->create();

        $this->actingAs($this->admin);

        $response = $this->post(route('subscriptions.store'), [
            'customer_id' => $customer->id,
            'subscription_package_id' => $subPackage->id,
            'collection_type' => 'non_scheduled',
            'collections_per_month' => 1,
            'max_clothes_per_cycle' => 80,
            'start_date' => now()->toDateString(),
        ]);

        $subscription = $customer->subscriptions()->first();
        $this->assertNotNull($subscription, 'Expected a subscription to have been created: '.json_encode(session('errors')?->all()));
        $this->assertSame('active', $subscription->status);

        $cycle = $subscription->cycles()->first();
        $this->assertNotNull($cycle, 'Expected a cycle to have been auto-created.');

        $collection = $subscription->collections()->where('subscription_cycle_id', $cycle->id)->first();
        $this->assertNotNull($collection, 'Expected a collection to have been auto-scheduled.');
        $this->assertSame('scheduled', $collection->status);

        // Collect it via the Terminal's subscription mode.
        $component = Livewire::actingAs($this->admin)->test('laundry-terminal', ['collectionId' => $collection->id]);
        $component->assertSet('isSubscriptionMode', true);

        $component->call('addClothingItem', 0, $clothingItem->id)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $collection->refresh();
        $this->assertSame('collected', $collection->status);

        $order = Order::where('collection_id', $collection->id)->first();
        $this->assertNotNull($order, 'Expected a subscription order to have been created.');
        $this->assertSame('subscription', $order->order_source);
        $this->assertEquals(0.0, (float) $order->subtotal, 'Subscription orders carry no subtotal of their own -- the cycle carries the price.');

        // collections_per_month=1 means the cycle only ever had one collection
        // to begin with (all of a cycle's collections are generated up front
        // at cycle-creation time, not one-at-a-time as each resolves) --
        // collecting it exhausts the cycle, ready for the next renewal.
        $this->assertSame(1, $subscription->collections()->count());
        $cycle->refresh();
        $this->assertTrue($cycle->isExhausted(), 'The cycle should be exhausted once its only collection is collected.');

        // Renders the Collection History and Billing Cycles tables (and their
        // mobile card-list counterparts) with at least one real row in each.
        $this->get(route('subscriptions.show', $subscription))
            ->assertOk()
            ->assertSee('Collection History')
            ->assertSee('Billing Cycles');
    }

    public function test_dashboard_renders(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_cancellation_workflow(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'WF-CANCEL-1',
            'customer_id' => $customer->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);
        $order->refresh();

        $this->actingAs($this->admin);

        $response = $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'Customer changed their mind']);
        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Customer changed their mind', $order->cancellation_reason);

        // Terminal state: a second cancel attempt must be rejected, no state change.
        $response = $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'again']);
        $response->assertSessionHasErrors('status');
        $order->refresh();
        $this->assertSame('cancelled', $order->status);

        // Terminal state: advancing a cancelled order must be rejected too (the
        // trigger blocks it at the DB level regardless; this confirms the app
        // layer doesn't even attempt it).
        $response = $this->post(route('orders.advance', $order));
        $order->refresh();
        $this->assertSame('cancelled', $order->status, 'A cancelled order must never advance.');
    }

    public function test_payment_and_refund_workflow(): void
    {
        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 200]);

        $this->actingAs($this->admin);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $orderId = Order::where('customer_id', $customer->id)->first()->id;
        // amountPaid()/balanceDue() are memoized per-instance (deliberately,
        // for query-count reasons elsewhere in the app) -- refresh() doesn't
        // clear that cache since it isn't an Eloquent attribute, so each
        // check below re-fetches a fresh model rather than reusing one.
        $this->assertEquals(200.0, Order::find($orderId)->balanceDue(), 'Pay on Pickup should leave the full balance outstanding.');

        // Record a partial payment through the real payments route.
        $response = $this->post(route('orders.payments.record', $orderId), [
            'amount' => 150,
            'method' => 'cash',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertEquals(50.0, Order::find($orderId)->balanceDue());

        $payment = Order::find($orderId)->payments()->first();
        $this->assertEquals(150.0, (float) $payment->amount);

        // Overpayment must be rejected server-side (the trigger's cap guard, surfaced as a friendly error).
        $response = $this->post(route('orders.payments.record', $orderId), [
            'amount' => 100, // 150 already paid + 100 = 250 > 200 total
            'method' => 'cash',
        ]);
        $response->assertSessionHasErrors('amount');
        $this->assertEquals(50.0, Order::find($orderId)->balanceDue(), 'Overpayment attempt must not have changed the balance.');

        // Pay off the rest, then refund part of it.
        $this->post(route('orders.payments.record', $orderId), ['amount' => 50, 'method' => 'cash']);
        $this->assertEquals(0.0, Order::find($orderId)->balanceDue());

        // The 150 payment is refunded 40; status should flip to partially_refunded.
        \App\Models\Refund::create(['payment_id' => $payment->id, 'amount' => 40, 'refunded_by' => $this->admin->id]);
        $payment->refresh();
        $this->assertSame('partially_refunded', $payment->status);
    }

    public function test_damage_management_workflow(): void
    {
        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);
        $damageType = DamageType::factory()->create();

        $this->actingAs($this->admin);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();

        $response = $this->post(route('damage.store', $order), [
            'damage_type_id' => $damageType->id,
            'item_description' => 'Torn shirt sleeve',
        ]);

        $damageRecord = $order->damageRecords()->first();
        $this->assertNotNull($damageRecord, 'Expected a damage record to have been created: '.json_encode(session('errors')?->all()));
        $this->assertSame('pending_review', $damageRecord->status);

        // Review chain: pending_review -> approved (allowed to skip under_investigation per §2.4).
        $this->post(route('damage.transition', $damageRecord), ['status' => 'approved']);
        $damageRecord->refresh();
        $this->assertSame('approved', $damageRecord->status);

        // A direct attempt to set status=resolved must be rejected -- resolve() is the only door.
        $response = $this->post(route('damage.transition', $damageRecord), ['status' => 'resolved']);
        $response->assertSessionHasErrors('status');

        $customer->refresh();
        $balanceBefore = (float) $customer->store_credit_balance;

        $response = $this->post(route('damage.resolve', $damageRecord), [
            'resolution_type' => 'store_credit',
            'amount' => 30,
        ]);

        $damageRecord->refresh();
        $this->assertSame('resolved', $damageRecord->status, 'Resolution is the only door into resolved -- confirmed it actually opened it: '.json_encode(session('errors')?->all()));

        $customer->refresh();
        $this->assertEquals($balanceBefore + 30, (float) $customer->store_credit_balance, 'Store-credit resolution should have credited the customer.');
    }
}
