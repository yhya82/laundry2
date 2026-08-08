<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Locks in three real fixes made during this project's security review, so a
 * future change can't silently reopen any of them:
 *
 * 1. Order-assignment scoping was enforced on read (index/show) but not on
 *    write (advance/cancel/pay) -- a scoped-out staff member could act on an
 *    order by ID even though they couldn't see it in their list.
 * 2. The Terminal's cart is an ordinary public Livewire property; a client
 *    could push a tampered price/quantity via the wire protocol directly,
 *    bypassing addPackage()'s own logic entirely.
 * 3. users.manage had no privilege ceiling -- a holder of that permission on
 *    a role other than Admin could grant Admin-equivalent access to anyone,
 *    including themselves.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
    }

    public function test_scoped_out_staff_cannot_advance_cancel_or_pay_an_unassigned_order(): void
    {
        Setting::set('order.assignment_enabled', 'true', 'order', 'boolean');

        // Laundry role: orders.view + orders.manage, deliberately NOT orders.assign.
        $laundryStaff = User::factory()->create();
        $laundryStaff->syncRoles(['Laundry']);

        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'SEC-1',
            'customer_id' => $customer->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
            'assigned_to' => null, // explicitly unassigned
        ]);

        $this->actingAs($laundryStaff);

        $this->get(route('orders.show', $order))->assertForbidden();
        $this->post(route('orders.advance', $order))->assertForbidden();
        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'x'])->assertForbidden();
        $this->post(route('orders.payments.record', $order), ['amount' => 10, 'method' => 'cash'])->assertForbidden();

        $order->refresh();
        $this->assertSame('received', $order->status, 'None of the blocked actions should have changed the order.');
    }

    public function test_scoped_staff_can_act_on_an_order_assigned_to_them(): void
    {
        Setting::set('order.assignment_enabled', 'true', 'order', 'boolean');

        $laundryStaff = User::factory()->create();
        $laundryStaff->syncRoles(['Laundry']);

        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'SEC-2',
            'customer_id' => $customer->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
            'assigned_to' => $laundryStaff->id,
        ]);

        $this->actingAs($laundryStaff);

        $this->get(route('orders.show', $order))->assertOk();
        $this->post(route('orders.cancel', $order), ['cancellation_reason' => 'x'])->assertRedirect();

        $order->refresh();
        $this->assertSame('cancelled', $order->status, 'A staff member should be able to act on an order actually assigned to them.');
    }

    public function test_orders_assign_holder_is_not_scoped(): void
    {
        Setting::set('order.assignment_enabled', 'true', 'order', 'boolean');

        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']); // Admin carries orders.assign

        $customer = Customer::factory()->create();
        $order = Order::create([
            'order_number' => 'SEC-3',
            'customer_id' => $customer->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
            'assigned_to' => null,
        ]);

        $this->actingAs($admin);

        $this->get(route('orders.show', $order))->assertOk();
        $this->post(route('orders.advance', $order))->assertRedirect();

        $order->refresh();
        $this->assertSame('sorting', $order->status);
    }

    public function test_terminal_cart_price_and_quantity_are_re_derived_server_side(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create(['base_price' => 100]);

        $this->actingAs($admin);

        $component = Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage');

        // Simulate a tampered wire:model sync -- the client pushing a
        // fabricated price/quantity directly, bypassing addPackage()'s own
        // (price locked to the real package, quantity starts at 1) logic.
        $component->set('cart.0.price', 1)
            ->set('cart.0.quantity', 999)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->first();
        $line = $order->packageLines()->first();

        $this->assertEquals(100.0, (float) $line->package_price_snapshot, 'Price must be re-derived from the real package, not the tampered client value.');
        $this->assertSame(100, $line->quantity, 'Quantity must be capped at a sane maximum, not trusted verbatim from the client.');
        $this->assertEquals(10000.0, (float) $order->subtotal, 'Order subtotal must reflect the real price x the capped quantity (100 x 100), not 1 x 999.');
    }

    public function test_users_manage_holder_on_a_non_admin_role_cannot_grant_admin_equivalent_access(): void
    {
        // Simulates the exact scenario the fix defends against: users.manage
        // delegated to a role other than Admin (not the case by default
        // today, but nothing should assume it never will be).
        $managerRole = Role::create(['name' => 'Manager', 'guard_name' => 'web']);
        $managerRole->givePermissionTo('users.manage');

        $manager = User::factory()->create();
        $manager->syncRoles(['Manager']);

        $victim = User::factory()->create();
        $victim->syncRoles(['Laundry']);

        $this->actingAs($manager);

        // Cannot grant themselves the Admin role.
        $response = $this->put(route('users.roles.update', $manager), ['roles' => ['Admin']]);
        $response->assertSessionHasErrors('roles');
        $this->assertFalse($manager->fresh()->hasRole('Admin'));

        // Cannot grant someone else the Admin role either.
        $response = $this->put(route('users.roles.update', $victim), ['roles' => ['Admin']]);
        $response->assertSessionHasErrors('roles');
        $this->assertFalse($victim->fresh()->hasRole('Admin'));

        // Cannot grant users.manage to a role via the permissions matrix, either.
        $anotherRole = Role::create(['name' => 'Supervisor', 'guard_name' => 'web']);
        $response = $this->put(route('roles.permissions.update', $anotherRole), ['permissions' => ['users.manage']]);
        $response->assertSessionHasErrors('permissions');
        $this->assertFalse($anotherRole->fresh()->hasPermissionTo('users.manage'));

        // Cannot reset an Admin's password.
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $response = $this->put(route('users.setPassword', $admin), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $response->assertSessionHasErrors('user');
    }

    public function test_an_admin_can_still_do_all_of_the_above(): void
    {
        // The fix must not have accidentally locked Admin out of its own screen.
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $victim = User::factory()->create();
        $victim->syncRoles(['Laundry']);

        $this->actingAs($admin);

        $response = $this->put(route('users.roles.update', $victim), ['roles' => ['Admin', 'Laundry']]);
        $response->assertSessionDoesntHaveErrors();
        $this->assertTrue($victim->fresh()->hasRole('Admin'));

        $response = $this->put(route('users.setPassword', $admin), [
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);
        $response->assertSessionDoesntHaveErrors();
    }

    /**
     * Same class of bug as the cart price/quantity test above, found by
     * checking the parallel subscription-mode code path for the same
     * pattern: a tampered negative quantity on $subscriptionClothes could
     * zero out clothesCount, suppressing a real over-allowance charge
     * entirely -- not just mispricing a line, but skipping a required
     * charge outright.
     */
    public function test_tampered_subscription_clothes_quantity_cannot_suppress_a_required_overage_charge(): void
    {
        Setting::set('subscription.charge_for_cycle_overage', 'true', 'subscription', 'boolean');

        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin);

        $customer = Customer::factory()->subscription()->create();
        $subPackage = \App\Models\SubscriptionPackage::factory()->create();
        LaundryPackage::factory()->create();
        $clothingItem = \App\Models\ClothingItem::factory()->create();

        $subscription = \App\Models\Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $subPackage->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => 1,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 2,
        ]);
        $cycle = \App\Models\SubscriptionCycle::create([
            'subscription_id' => $subscription->id,
            'starts_on' => now(),
            'monthly_price_snapshot' => 300,
            'max_clothes_snapshot' => 2,
        ]);
        \App\Support\CollectionScheduler::generateCollections($subscription, $cycle, 'non_scheduled', now(), 1);
        $collection = $subscription->collections()->first();

        $component = Livewire::test('laundry-terminal', ['collectionId' => $collection->id])
            ->call('addClothingItem', 0, $clothingItem->id); // real quantity: 1, well within the cap of 2

        // Tamper the quantity to a large negative number -- if unsanitized,
        // clothesCount (which sums subscriptionClothes quantities directly)
        // goes negative, cycleOverageCount clamps to 0 via max(0, ...), and
        // the required-overage-charge validation never triggers even though
        // 500 real items are being claimed as collected.
        $component->set('subscriptionClothes.0.quantity', -500)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('collection_id', $collection->id)->first();
        $this->assertNotNull($order);

        $clothesLine = $order->packageLines()->first()->clothesLines()->first();
        $this->assertSame(1, $clothesLine->quantity, 'Quantity must be re-derived/clamped, not trusted from the client.');
    }
}
