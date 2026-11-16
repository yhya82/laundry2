<?php

namespace Tests\Feature;

use App\Models\ClothingItem;
use App\Models\Customer;
use App\Models\LaundryPackage;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Support\CollectionScheduler;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Terminal's receipt-printer success screen (orders/_receipt-printer).
 * The stage animation is client-side (Alpine); these cover what the server
 * renders into it.
 */
class ReceiptPrinterTest extends TestCase
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

    public function test_a_walk_in_order_prints_its_receipt_with_all_three_buttons(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Awa Jallow']);
        $package = LaundryPackage::factory()->create(['name' => 'Wash & Fold', 'base_price' => 150]);
        $item = ClothingItem::factory()->create(['name' => 'Kaftan']);

        $component = Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->call('addClothingItem', 0, $item->id)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors();

        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $component
            ->assertSet('lastSubmittedOrderId', $order->id)
            ->assertSeeHtml('data-receipt-printer')
            ->assertSeeInOrder([
                'Walk-in Order',
                $order->receipt->receipt_number,
                $order->order_number,
                'Awa Jallow',
                'Wash & Fold',
                'Kaftan',
                'Total',
                'GMD 150.00',
                'Balance Due at Pickup',
            ])
            ->assertSee('Order complete')
            ->assertSee('View Order')
            ->assertSee('Print Receipt')
            ->assertSee('New Order')
            ->assertSeeHtml('href="'.route('orders.show', $order).'"');

        // Printing happens client-side from the on-screen paper -- the
        // reprint counter (bumped only by /orders/{id}/receipt) is untouched.
        $this->assertSame(0, $order->receipt->fresh()->reprint_count);
    }

    public function test_a_subscription_collection_prints_its_receipt(): void
    {
        $customer = Customer::factory()->subscription()->create();
        $subPackage = SubscriptionPackage::factory()->create(['name' => 'Gold Plan', 'monthly_price' => 500]);
        LaundryPackage::factory()->create(); // container package subscription mode needs
        $item = ClothingItem::factory()->create(['name' => 'Shirt']);

        $subscription = Subscription::create([
            'customer_id' => $customer->id,
            'subscription_package_id' => $subPackage->id,
            'status' => 'active',
            'start_date' => now(),
            'collections_per_month' => 1,
            'collection_type' => 'non_scheduled',
            'max_clothes_per_cycle' => 80,
        ]);
        CollectionScheduler::scheduleFirstCycle($subscription);
        $collection = $subscription->collections()->first();

        Livewire::test('laundry-terminal', ['collectionId' => $collection->id])
            ->call('addClothingItem', 0, $item->id)
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertHasNoErrors()
            ->assertSee('Collection recorded')
            ->assertSeeInOrder(['Subscription Collection', 'Shirt', 'Subscription Plan (Gold Plan)', 'GMD 500.00', 'Cycle Balance Due']);
    }

    public function test_a_failed_save_puts_nothing_on_the_paper(): void
    {
        $customer = Customer::factory()->create();

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder') // empty cart
            ->assertHasErrors('cart')
            ->assertSet('lastSubmittedOrderId', null)
            ->assertDontSeeHtml('class="rcpt"');
    }

    public function test_new_order_clears_the_printer_and_leaves_the_terminal_ready(): void
    {
        $customer = Customer::factory()->create();
        $package = LaundryPackage::factory()->create();

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder')
            ->assertSeeHtml('class="rcpt"')
            ->set('lastSubmittedOrderId', null) // what New Order does
            ->assertDontSeeHtml('class="rcpt"')
            ->assertSet('customerId', null)
            ->assertSet('cart', []);
    }

    public function test_the_printable_receipt_page_still_renders_from_the_shared_partial(): void
    {
        $customer = Customer::factory()->create(['full_name' => 'Lamin Ceesay']);
        $package = LaundryPackage::factory()->create(['base_price' => 80]);

        Livewire::test('laundry-terminal', ['customerId' => $customer->id])
            ->set('selectedPackageId', (string) $package->id)
            ->call('addPackage')
            ->set('paymentTiming', 'pay_on_pickup')
            ->call('submitOrder');

        $order = Order::where('customer_id', $customer->id)->firstOrFail();

        $this->get(route('orders.receipt', $order))
            ->assertOk()
            ->assertSee('.rcpt-row', false)
            ->assertSeeInOrder([$order->order_number, 'Lamin Ceesay', 'GMD 80.00']);

        $this->assertSame(1, $order->receipt->fresh()->reprint_count, 'Opening the printable page still counts as a print.');
    }
}
