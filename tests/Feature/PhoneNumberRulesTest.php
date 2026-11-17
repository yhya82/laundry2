<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 7 -> 9 digit phone numbers across the app: 7-digit entries are saved in
 * their 9-digit form, and a number stored before the change counts as the
 * same line as its 9-digit form for duplicates and search.
 */
class PhoneNumberRulesTest extends TestCase
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

    protected function storeCustomer(string $phone)
    {
        return $this->from(route('customers.create'))->post(route('customers.store'), [
            'full_name' => 'New Customer', 'phone' => $phone, 'customer_type' => 'walk_in',
        ]);
    }

    public function test_a_7_digit_number_is_saved_in_its_9_digit_form(): void
    {
        $this->storeCustomer('519 0199')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', ['phone' => '+220835190199']);
    }

    public function test_an_unknown_9_digit_prefix_is_refused(): void
    {
        $this->storeCustomer('555123456')
            ->assertSessionHasErrors(['phone' => 'Enter a valid number: 7 digits, or 9 digits starting with 83, 86, 87 or 9.']);
    }

    public function test_the_9_digit_form_of_a_stored_7_digit_number_is_a_duplicate(): void
    {
        Customer::factory()->create(['phone' => '+2205190199']); // stored before the change

        $this->storeCustomer('835190199')->assertSessionHasErrors(['phone' => 'A customer with this phone number already exists.']);
        $this->storeCustomer('5190199')->assertSessionHasErrors(['phone' => 'A customer with this phone number already exists.']);
        $this->assertSame(1, Customer::count());
    }

    public function test_the_7_digit_form_of_a_stored_9_digit_number_is_a_duplicate(): void
    {
        Customer::factory()->create(['phone' => '+220835190199']);

        $this->storeCustomer('5190199')->assertSessionHasErrors('phone');
        $this->assertSame(1, Customer::count());
    }

    public function test_a_different_prefix_is_a_different_number(): void
    {
        Customer::factory()->create(['phone' => '+2205190199']);

        // 5 maps to 83, so 86 5190199 was never this customer's number.
        $this->storeCustomer('865190199')->assertSessionHasNoErrors();
        $this->assertSame(2, Customer::count());
    }

    public function test_editing_an_old_7_digit_customer_converts_their_number(): void
    {
        $customer = Customer::factory()->create(['phone' => '+2205190199']);

        $this->put(route('customers.update', $customer), [
            'full_name' => $customer->full_name, 'phone' => '5190199', 'customer_type' => 'walk_in',
        ])->assertSessionHasNoErrors();

        $this->assertSame('+220835190199', $customer->fresh()->phone, 'Not refused as a duplicate of itself.');
    }

    public function test_search_finds_a_customer_by_either_form(): void
    {
        $old = Customer::factory()->create(['full_name' => 'Stored As Seven', 'phone' => '+2205190199']);
        $new = Customer::factory()->create(['full_name' => 'Stored As Nine', 'phone' => '+220877123456']);

        $this->get(route('customers.index', ['q' => '835190199']))->assertSee('Stored As Seven');
        $this->get(route('customers.index', ['q' => '7123456']))->assertSee('Stored As Nine');

        Livewire::test('laundry-terminal')->set('customerSearch', '835 190 199')->assertSee('Stored As Seven');
        Livewire::test('laundry-terminal')->set('customerSearch', '7123456')->assertSee('Stored As Nine');
    }

    public function test_the_terminal_refuses_a_new_customer_whose_number_exists_in_the_other_form(): void
    {
        Customer::factory()->create(['phone' => '+2205190199']);

        Livewire::test('laundry-terminal')
            ->set('showNewCustomerForm', true)
            ->set('newCustomerName', 'Same Person')
            ->set('newCustomerPhone', '835190199')
            ->assertHasErrors(['newCustomerPhone'])
            ->assertSet('newCustomerPhone', '+220835190199');
    }

    public function test_staff_phone_numbers_follow_the_same_rules(): void
    {
        User::factory()->create(['phone' => '+2205190199']);

        $this->from(route('users.index'))->post(route('users.store'), [
            'name' => 'New Staff', 'email' => 'staff@laundry.test', 'phone' => '835190199',
            'password' => 'temporary-pass', 'password_confirmation' => 'temporary-pass', 'roles' => ['Laundry'],
        ])->assertSessionHasErrors(['phone' => 'A staff member with this phone number already exists.']);

        $this->post(route('users.store'), [
            'name' => 'Other Staff', 'email' => 'other@laundry.test', 'phone' => '6123456',
            'password' => 'temporary-pass', 'password_confirmation' => 'temporary-pass', 'roles' => ['Laundry'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'other@laundry.test', 'phone' => '+220866123456']);
    }

    public function test_the_collection_stand_in_phone_follows_the_same_rules(): void
    {
        $order = \App\Models\Order::create([
            'order_number' => \App\Support\Numbering::nextOrderNumber(),
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 0, // nothing owed, so no payment step
        ]);
        foreach (['wash', 'completed'] as $status) {
            $order->status = $status;
            $order->save();
        }

        $collect = fn (string $phone) => $this->from(route('orders.show', $order))->post(route('orders.advance', $order), [
            'collected_by_type' => 'other', 'collected_by_name' => 'Brother', 'collected_by_phone' => $phone,
        ]);

        $collect('555123456')->assertSessionHasErrors('collected_by_phone');
        $collect('5190199')->assertSessionHasNoErrors();

        $this->assertSame('+220835190199', $order->fresh()->collected_by_phone);
    }

    public function test_the_business_phone_is_converted_too(): void
    {
        $this->put(route('settings.update'), ['group' => 'general', 'business_name' => 'Laundry', 'phone' => '7123456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('+220877123456', Setting::get('branding.phone'));
    }
}
