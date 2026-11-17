<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Terminal's subscription slide-over: creating a subscription customer
 * (or a subscription for an existing one) in place, with no page reload.
 */
class SubscriptionTerminalPanelTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionPackage $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin);

        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->package = SubscriptionPackage::factory()->create([
            'collections_per_month' => 3,
            'max_clothes_per_cycle' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function openPanelForNewCustomer(string $phone = '+220870333001')
    {
        return Livewire::test('laundry-terminal')
            ->set('showNewCustomerForm', true)
            ->set('newCustomerName', 'Panel Customer')
            ->set('newCustomerPhone', $phone)
            ->set('newCustomerType', 'subscription');
    }

    public function test_a_new_customer_and_subscription_are_created_together_and_the_first_visit_opens(): void
    {
        $component = $this->openPanelForNewCustomer()
            ->assertSee('New Subscription Customer')
            ->assertSeeInOrder(['Customer', 'Choose a package', $this->package->name, 'Details', 'Start date'])
            ->assertSeeHtml('wire:model.live="planPackageId" value="'.$this->package->id.'"')
            ->set('planPackageId', (string) $this->package->id)
            ->set('planCollectionType', 'non_scheduled')
            ->call('createSubscription')
            ->assertHasNoErrors();

        $customer = Customer::where('phone', '+220870333001')->first();
        $this->assertNotNull($customer);
        $this->assertSame('subscription', $customer->customer_type);

        $subscription = $customer->subscriptions()->first();
        $this->assertNotNull($subscription);
        $this->assertSame('2026-11-16', $subscription->start_date->toDateString());
        $this->assertSame(3, $subscription->collections_per_month, "The package's defaults are applied when it's picked.");
        $this->assertSame(60, $subscription->max_clothes_per_cycle);

        $firstVisit = $subscription->currentCycle()->collections()->orderBy('id')->first();

        $component
            ->assertSet('customerId', $customer->id)
            ->assertSet('collectionId', $firstVisit->id)
            ->assertSet('creatingSubscriptionCustomer', false)
            ->assertDontSee('New Subscription Customer')
            ->assertSee('Record Collection');
    }

    public function test_closing_the_panel_saves_nothing_and_keeps_what_was_typed(): void
    {
        $this->openPanelForNewCustomer()
            ->call('closeSubscriptionPanel')
            ->assertSet('creatingSubscriptionCustomer', false)
            ->assertSet('newCustomerType', '')
            ->assertSet('newCustomerName', 'Panel Customer')
            ->assertDontSee('New Subscription Customer');

        $this->assertDatabaseMissing('customers', ['phone' => '+220870333001']);
    }

    public function test_choosing_walk_in_adds_and_selects_the_customer_without_a_button(): void
    {
        Livewire::test('laundry-terminal')
            ->set('showNewCustomerForm', true)
            ->set('newCustomerName', 'Walk-in Customer')
            ->set('newCustomerPhone', '870333002')
            ->set('newCustomerType', 'walk_in')
            ->assertHasNoErrors()
            ->assertSet('usingPendingCustomer', true)
            ->assertSet('showNewCustomerForm', false)
            ->assertSet('newCustomerPhone', '+220870333002')
            ->assertDontSee('Add &amp; select', false);

        // Still staged, not saved -- written with the order, as before.
        $this->assertDatabaseMissing('customers', ['phone' => '+220870333002']);
    }

    public function test_choosing_walk_in_with_invalid_details_shows_errors_and_clears_the_choice(): void
    {
        Livewire::test('laundry-terminal')
            ->set('showNewCustomerForm', true)
            ->set('newCustomerName', '')
            ->set('newCustomerPhone', '12')
            ->set('newCustomerType', 'walk_in')
            ->assertHasErrors(['newCustomerName', 'newCustomerPhone'])
            ->assertSet('newCustomerType', '')
            ->assertSet('usingPendingCustomer', false)
            ->assertSet('showNewCustomerForm', true);
    }

    public function test_selecting_an_existing_customer_without_a_subscription_never_opens_the_panel(): void
    {
        $customer = Customer::factory()->subscription()->create();

        // Both ways in: arriving with ?customer= and picking them from search.
        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->assertDontSeeHtml('wire:key="subscription-panel"')
            ->assertSeeHtml('data-no-subscription-notice')
            ->assertSee('No active subscription.');

        Livewire::test('laundry-terminal')
            ->set('customerSearch', $customer->full_name)
            ->call('selectCustomer', $customer->id)
            ->assertDontSeeHtml('wire:key="subscription-panel"')
            ->assertSeeHtml('data-no-subscription-notice');
    }

    public function test_set_up_subscription_opens_the_plan_only_panel_on_request(): void
    {
        $customer = Customer::factory()->subscription()->create();

        $component = Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->call('openSubscriptionSetup')
            ->assertSeeHtml('wire:key="subscription-panel"')
            ->assertSee('Set Up Subscription')
            ->assertSee($customer->full_name)
            ->set('planPackageId', (string) $this->package->id)
            ->call('createSubscription')
            ->assertHasNoErrors();

        $subscription = $customer->subscriptions()->first();
        $this->assertNotNull($subscription);

        $component
            ->assertSet('collectionId', $subscription->currentCycle()->collections()->orderBy('scheduled_date')->first()->id)
            ->assertSet('settingUpSubscription', false)
            ->assertDontSeeHtml('wire:key="subscription-panel"');
    }

    public function test_closing_the_plan_only_panel_keeps_the_existing_customer_selected(): void
    {
        $customer = Customer::factory()->subscription()->create();

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->call('openSubscriptionSetup')
            ->call('closeSubscriptionPanel')
            ->assertSet('customerId', $customer->id)
            ->assertSet('settingUpSubscription', false)
            ->assertDontSeeHtml('wire:key="subscription-panel"')
            ->assertSeeHtml('data-no-subscription-notice');

        $this->assertSame(0, $customer->subscriptions()->count());
    }

    public function test_walk_in_order_from_the_notice_continues_without_a_subscription(): void
    {
        $customer = Customer::factory()->subscription()->create();

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('forceWalkIn', true)
            ->assertDontSeeHtml('data-no-subscription-notice')
            ->assertDontSeeHtml('wire:key="subscription-panel"');
    }

    public function test_a_customer_with_a_paused_subscription_is_told_to_resume_it_not_offered_setup(): void
    {
        $customer = Customer::factory()->subscription()->create();
        \App\Models\Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $this->package->id,
            'status' => 'paused',
            'start_date' => now(),
            'collections_per_month' => 1,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 60,
        ]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->assertSee('Their subscription is paused.')
            ->assertDontSee('Set up subscription')
            ->call('openSubscriptionSetup')
            ->assertDontSeeHtml('wire:key="subscription-panel"');
    }

    public function test_when_sign_ups_are_disabled_neither_the_customer_nor_the_subscription_is_saved(): void
    {
        Setting::set('subscription.allow_new_signups', 'false', 'subscription', 'boolean');

        $this->openPanelForNewCustomer()
            ->set('planPackageId', (string) $this->package->id)
            ->call('createSubscription')
            ->assertHasErrors('plan')
            ->assertSet('creatingSubscriptionCustomer', true);

        $this->assertDatabaseMissing('customers', ['phone' => '+220870333001']);
    }

    public function test_a_failure_after_the_customer_and_subscription_rows_exist_rolls_back_everything(): void
    {
        // Lets the real service write the subscription and its first cycle,
        // then fails -- the worst moment, with every row already inserted.
        $this->app->instance(
            \App\Services\SubscriptionCreationService::class,
            new class(app(\App\Services\CustomerBalanceService::class)) extends \App\Services\SubscriptionCreationService
            {
                public function create(Customer $customer, array $plan): \App\Models\Subscription
                {
                    parent::create($customer, $plan);

                    throw new \App\Services\SubscriptionCreationException('Simulated failure after insert.', 'test');
                }
            }
        );

        $this->openPanelForNewCustomer()
            ->set('planPackageId', (string) $this->package->id)
            ->call('createSubscription')
            ->assertHasErrors('plan')
            ->assertSet('creatingSubscriptionCustomer', true)
            ->assertSet('customerId', null);

        $this->assertDatabaseMissing('customers', ['phone' => '+220870333001']);
        $this->assertDatabaseCount('subscriptions', 0);
        $this->assertDatabaseCount('subscription_cycles', 0);
        $this->assertDatabaseCount('collections', 0);
    }

    public function test_a_phone_number_already_in_use_stops_the_panel_from_opening(): void
    {
        Customer::factory()->create(['phone' => '+220870333001']);

        $this->openPanelForNewCustomer('870333001')
            ->assertHasErrors('newCustomerPhone')
            ->assertSet('creatingSubscriptionCustomer', false)
            ->assertSet('newCustomerType', '')
            ->assertSet('showNewCustomerForm', true);

        $this->assertSame(1, Customer::where('phone', '+220870333001')->count());
    }

    public function test_a_phone_number_taken_while_the_panel_is_open_is_still_rejected_on_create(): void
    {
        $component = $this->openPanelForNewCustomer()->assertSet('creatingSubscriptionCustomer', true);

        // Someone else saves the same number in the meantime.
        Customer::factory()->create(['phone' => '+220870333001']);

        $component
            ->set('planPackageId', (string) $this->package->id)
            ->call('createSubscription')
            ->assertHasErrors('newCustomerPhone');

        $this->assertSame(1, Customer::where('phone', '+220870333001')->count());
    }

    public function test_picking_subscription_with_an_invalid_phone_shows_the_error_and_stays_in_the_pop_up(): void
    {
        Livewire::test('laundry-terminal')
            ->set('showNewCustomerForm', true)
            ->set('newCustomerName', 'Short Number')
            ->set('newCustomerPhone', '55512345') // 8 digits
            ->set('newCustomerType', 'subscription')
            ->assertHasErrors(['newCustomerPhone' => 'regex'])
            ->assertSet('creatingSubscriptionCustomer', false)
            ->assertSet('newCustomerType', '')
            ->assertSet('showNewCustomerForm', true);
    }

    public function test_the_phone_is_checked_live_as_soon_as_the_field_is_left(): void
    {
        $component = Livewire::test('laundry-terminal')->set('showNewCustomerForm', true);

        // 8 or 10 digits, too short, and 9 digits with an unknown prefix (55).
        foreach (['55512345', '5551234567', '12', '555123456'] as $badNumber) {
            $component->set('newCustomerPhone', $badNumber)->assertHasErrors(['newCustomerPhone' => 'regex']);
        }

        // 7 digits are saved in their 9-digit form; 9 starting with 83/86/87/9 as typed.
        foreach (['5551234' => '+220835551234', '835 123 456' => '+220835123456', '+220 555-1234' => '+220835551234', '9123456' => '+2209123456'] as $typed => $stored) {
            $component->set('newCustomerPhone', $typed)
                ->assertHasNoErrors('newCustomerPhone')
                ->assertSet('newCustomerPhone', $stored);
        }

        // An emptied field isn't flagged until they try to continue.
        $component->set('newCustomerPhone', '')->assertHasNoErrors('newCustomerPhone');
    }

    public function test_up_to_three_packages_show_as_cards_and_more_switch_to_a_dropdown(): void
    {
        SubscriptionPackage::factory()->count(2)->create();

        $this->openPanelForNewCustomer()
            ->assertSeeHtml('role="radiogroup"')
            ->assertDontSeeHtml('<select id="sp_package"');

        SubscriptionPackage::factory()->create(['name' => 'Fourth Plan']);

        $this->openPanelForNewCustomer()
            ->assertSeeHtml('<select id="sp_package"')
            ->assertDontSeeHtml('role="radiogroup"')
            ->set('planPackageId', (string) $this->package->id)
            ->assertSee('Clothes max')
            ->call('createSubscription')
            ->assertHasNoErrors();

        $this->assertNotNull(Customer::where('phone', '+220870333001')->first()?->subscriptions()->first());
    }

    public function test_create_is_disabled_until_a_package_is_picked(): void
    {
        $submitIsDisabled = fn ($component) => (bool) preg_match('/type="submit"\s+disabled\b/', $component->html());

        $component = $this->openPanelForNewCustomer()->assertSee('Select a package to continue.');
        $this->assertTrue($submitIsDisabled($component));

        $component->set('planPackageId', (string) $this->package->id)->assertDontSee('Select a package to continue.');
        $this->assertFalse($submitIsDisabled($component));
    }

    public function test_a_package_is_required(): void
    {
        // The button is disabled without one, but the server still refuses.
        $this->openPanelForNewCustomer()
            ->call('createSubscription')
            ->assertHasErrors('planPackageId');

        $this->assertDatabaseMissing('customers', ['phone' => '+220870333001']);
    }

    public function test_the_subscriptions_page_always_starts_a_new_subscription_today(): void
    {
        $customer = Customer::factory()->subscription()->create();

        $this->post(route('subscriptions.store'), [
            'customer_id' => $customer->id,
            'subscription_package_id' => $this->package->id,
            'collection_type' => 'scheduled',
            'collections_per_month' => 2,
            'max_clothes_per_cycle' => 60,
            'start_date' => '2027-03-01',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('2026-11-16', $customer->subscriptions()->first()->start_date->toDateString());
    }
}
