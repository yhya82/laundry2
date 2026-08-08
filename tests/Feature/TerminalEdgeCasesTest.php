<?php

namespace Tests\Feature;

use App\Models\ClothingItem;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\SubscriptionCycle;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CollectionScheduler;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TerminalEdgeCasesTest extends TestCase
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

    public function test_a_discount_over_the_configured_cap_is_rejected(): void
    {
        Setting::set('order.discount_enabled', 'true', 'order', 'boolean');
        Setting::set('order.max_discount_percent', '10', 'order', 'integer');

        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        $component = Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('discount', 20) // 20% of a 100 subtotal, cap is 10%
            ->set('discountReason', 'Loyalty')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder');

        $component->assertHasErrors('discount');
        $this->assertNull(Order::where('customer_id', $customer->id)->first());
    }

    public function test_a_discount_within_the_cap_is_accepted_and_reduces_the_total(): void
    {
        Setting::set('order.discount_enabled', 'true', 'order', 'boolean');
        Setting::set('order.max_discount_percent', '10', 'order', 'integer');

        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('discount', 5)
            ->set('discountReason', 'Loyalty')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertEquals(5.0, (float) $order->discount);
        $this->assertEquals(95.0, (float) $order->total_amount);
    }

    public function test_walk_in_extra_charge_requires_a_reason_when_enabled(): void
    {
        Setting::set('subscription.walkin_extra_charge_enabled', 'true', 'subscription', 'boolean');

        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('walkInExtraCharge', 15)
            // reason deliberately left blank
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasErrors('walkInExtraChargeReason');

        $this->assertNull(Order::where('customer_id', $customer->id)->first());
    }

    public function test_walk_in_extra_charge_is_applied_with_a_reason(): void
    {
        Setting::set('subscription.walkin_extra_charge_enabled', 'true', 'subscription', 'boolean');

        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('walkInExtraCharge', 15)
            ->set('walkInExtraChargeReason', 'Heavily soiled items')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertEquals(15.0, (float) $order->extra_charge);
        $this->assertSame('Heavily soiled items', $order->extra_charge_reason);
        $this->assertEquals(115.0, (float) $order->total_amount);
    }

    public function test_walk_in_extra_charge_is_ignored_when_the_setting_is_disabled_even_if_the_client_sends_it(): void
    {
        Setting::set('subscription.walkin_extra_charge_enabled', 'false', 'subscription', 'boolean');

        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('walkInExtraCharge', 15) // set directly, bypassing the (hidden, since disabled) UI field
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertEquals(0.0, (float) $order->extra_charge, 'A disabled setting must be enforced server-side, not just hidden in the UI.');
    }

    public function test_store_credit_is_applied_and_capped_at_the_available_balance(): void
    {
        $customer = Customer::factory()->create();
        $customer->creditTransactions()->create(['type' => 'credit', 'amount' => 30, 'balance_after' => 30]);
        $customer = $customer->fresh();
        $this->assertEquals(30.0, (float) $customer->store_credit_balance);

        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        // Attempt to apply more credit (50) than the customer actually has (30).
        $component = Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 50)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder');

        $component->assertHasErrors('creditToApply');
        $this->assertNull(Order::where('customer_id', $customer->id)->first());

        // Apply exactly what's available instead.
        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 30)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $payment = $order->payments()->first();
        $this->assertEquals(30.0, (float) $payment->credit_applied);
        $this->assertEquals(70.0, $order->balanceDue(), '100 total - 30 credit = 70 still due.');

        $customer = $customer->fresh();
        $this->assertEquals(0.0, (float) $customer->store_credit_balance, 'The full 30 balance should have been debited.');
    }

    public function test_subscription_over_allowance_requires_a_charge_when_the_setting_is_enabled(): void
    {
        Setting::set('subscription.charge_for_cycle_overage', 'true', 'subscription', 'boolean');

        $customer = Customer::factory()->subscription()->create();
        $subPackage = SubscriptionPackage::factory()->create();
        LaundryPackage::factory()->create();
        $clothingItem = ClothingItem::factory()->create();

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $subPackage->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => 1,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 2, // tiny cap, easy to exceed
        ]);
        $cycle = SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => now(),
            'monthly_price_snapshot' => 300,
            'max_clothes_snapshot' => 2,
        ]);
        CollectionScheduler::generateCollections($subscription, $cycle, 'non_scheduled', now(), 1);
        $collection = $subscription->collections()->first();

        $component = Livewire::test('laundry-terminal', ['collectionId' => $collection->id]);
        // Add 5 clothing items against a cap of 2 -- 3 over.
        for ($i = 0; $i < 5; $i++) {
            $component->call('addClothingItem', 0, $clothingItem->id);
        }

        $component->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasErrors('cycleOverageCharge');

        $this->assertNull(Order::where('collection_id', $collection->id)->first());

        // Now supply the required overage charge.
        $component->set('cycleOverageCharge', 20)
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('collection_id', $collection->id)->first();
        $this->assertNotNull($order);
        $this->assertEquals(20.0, (float) $order->cycle_overage_charge);
    }

    public function test_credit_and_cash_can_partially_cover_the_same_order_with_the_real_method_recorded(): void
    {
        $customer = Customer::factory()->create();
        $customer->creditTransactions()->create(['type' => 'credit', 'amount' => 40, 'balance_after' => 40]);
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        // 40 credit + 60 cash = 100, fully covering the order -- but since
        // real cash was actually involved, the payment's method should be
        // the chosen method ('card' here), not 'store_credit'.
        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 40)
            ->set('paymentTiming', 'pay_now')
            ->set('customAmount', 60)
            ->set('paymentMethod', 'card')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $payment = $order->payments()->first();

        $this->assertEquals(100.0, (float) $payment->amount);
        $this->assertEquals(40.0, (float) $payment->credit_applied);
        $this->assertSame('card', $payment->method, 'Real cash was involved -- the method should be what staff chose, not store_credit.');
        $this->assertEquals(0.0, $order->balanceDue());

        $customer = $customer->fresh();
        $this->assertEquals(0.0, (float) $customer->store_credit_balance);
    }

    public function test_credit_alone_fully_covering_an_order_is_recorded_as_store_credit_regardless_of_the_selected_method(): void
    {
        $customer = Customer::factory()->create();
        $customer->creditTransactions()->create(['type' => 'credit', 'amount' => 100, 'balance_after' => 100]);
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 100)
            ->set('paymentTiming', 'pay_now')
            ->set('paymentMethod', 'card') // chosen, but no cash ends up being collected
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $payment = $order->payments()->first();

        $this->assertSame('store_credit', $payment->method, 'No real cash/card was collected -- method should reflect that, not the dropdown choice.');
        $this->assertEquals(0.0, $order->balanceDue());
    }

    public function test_store_credit_can_be_applied_even_on_pay_on_pickup_leaving_only_the_remainder_due(): void
    {
        $customer = Customer::factory()->create();
        $customer->creditTransactions()->create(['type' => 'credit', 'amount' => 30, 'balance_after' => 30]);
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 30)
            ->set('paymentTiming', 'pay_on_pickup') // no cash/card collected now
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $payment = $order->payments()->first();

        $this->assertEquals(30.0, (float) $payment->amount, 'Credit should be settled immediately even though cash is deferred.');
        $this->assertEquals(70.0, $order->balanceDue(), '100 total - 30 credit, with the rest due on pickup.');

        $customer = $customer->fresh();
        $this->assertEquals(0.0, (float) $customer->store_credit_balance);
    }

    public function test_store_credit_disabled_by_setting_is_never_applied_even_if_the_client_sends_it(): void
    {
        Setting::set('payment.store_credit_enabled', 'false', 'payment', 'boolean');

        $customer = Customer::factory()->create();
        $customer->creditTransactions()->create(['type' => 'credit', 'amount' => 100, 'balance_after' => 100]);
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('creditToApply', 100)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertEquals(100.0, $order->balanceDue(), 'Store credit is disabled -- nothing should have been applied, even though the customer has a balance and the client asked for it.');

        $customer = $customer->fresh();
        $this->assertEquals(100.0, (float) $customer->store_credit_balance, 'Balance must be untouched.');
    }

    public function test_a_new_walk_in_customer_is_not_created_until_the_order_actually_submits(): void
    {
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        $component = Livewire::test('laundry-terminal')
            ->set('newCustomerName', 'Pending Customer')
            ->set('newCustomerPhone', '+2207001234')
            ->set('newCustomerType', 'walk_in')
            ->call('createCustomer');

        $component->assertHasNoErrors();
        $component->assertSet('usingPendingCustomer', true);
        // Deciding on a customer must not write them to the DB until the order actually commits.
        $this->assertDatabaseMissing('customers', ['phone' => '+2207001234']);

        $component->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $customer = Customer::where('phone', '+2207001234')->first();
        $this->assertNotNull($customer, 'The customer should exist now that the order actually went through.');
        $this->assertSame('Pending Customer', $customer->full_name);

        $order = Order::where('customer_id', $customer->id)->first();
        $this->assertNotNull($order, 'Customer and order should be linked -- both created in the same transaction.');
    }

    public function test_a_pending_customer_is_never_created_if_the_order_is_abandoned_before_submitting(): void
    {
        Livewire::test('laundry-terminal')
            ->set('newCustomerName', 'Abandoned Customer')
            ->set('newCustomerPhone', '+2207005678')
            ->set('newCustomerType', 'walk_in')
            ->call('createCustomer')
            ->assertSet('usingPendingCustomer', true);
        // ...and then the staff member just never adds a package or submits --
        // the request ends here, same as closing the browser tab.

        $this->assertDatabaseMissing('customers', ['phone' => '+2207005678']);
    }

    public function test_a_pending_customer_is_not_created_when_order_submission_fails_validation(): void
    {
        $component = Livewire::test('laundry-terminal')
            ->set('newCustomerName', 'Blocked Customer')
            ->set('newCustomerPhone', '+2207009999')
            ->set('newCustomerType', 'walk_in')
            ->call('createCustomer')
            ->assertSet('usingPendingCustomer', true);

        // No package ever added -- cart stays empty, submitOrder() must bail
        // before ever reaching the transaction that would create the customer.
        $component->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasErrors('cart');

        $this->assertDatabaseMissing('customers', ['phone' => '+2207009999']);
        $this->assertNull(Order::where('order_number', 'like', 'ORD-%')->first());
    }

    public function test_clearing_a_pending_customer_discards_the_staged_details(): void
    {
        $component = Livewire::test('laundry-terminal')
            ->set('newCustomerName', 'Discarded Customer')
            ->set('newCustomerPhone', '+2207001111')
            ->set('newCustomerType', 'walk_in')
            ->call('createCustomer')
            ->assertSet('usingPendingCustomer', true);

        $component->call('clearCustomer');

        $component->assertSet('usingPendingCustomer', false);
        $component->assertSet('newCustomerName', '');
        $component->assertSet('newCustomerPhone', '');
        $this->assertNull($component->get('selectedCustomer'));
    }

    public function test_a_new_subscription_type_customer_still_creates_immediately_not_deferred(): void
    {
        // Deliberately different from the walk-in path: addSubscriptionPackage()
        // needs a real customer id the moment a plan is picked, well before any
        // order/collection submission exists to defer into.
        $component = Livewire::test('laundry-terminal')
            ->set('newCustomerName', 'Immediate Sub Customer')
            ->set('newCustomerPhone', '+2207002222')
            ->set('newCustomerType', 'subscription')
            ->call('createCustomer');

        $component->assertHasNoErrors();
        $component->assertSet('usingPendingCustomer', false);
        $this->assertDatabaseHas('customers', ['phone' => '+2207002222', 'customer_type' => 'subscription']);
    }
}
