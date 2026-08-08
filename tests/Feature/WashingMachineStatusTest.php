<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\WashingMachine;
use Database\Seeders\PermissionsAndRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the /washing-machines/status endpoint the order page's "Select
 * Washing Machine" panel polls on open, so its busy/idle snapshot reflects
 * the DB at the moment of choice instead of whatever was true when the order
 * page itself was first rendered.
 */
class WashingMachineStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsAndRolesSeeder::class);
        $admin = User::factory()->create();
        $admin->syncRoles(['Admin']);
        $this->actingAs($admin->fresh());
    }

    public function test_status_reports_an_idle_machine_as_not_busy(): void
    {
        $machine = WashingMachine::create(['name' => 'M1', 'is_active' => true]);

        $response = $this->getJson(route('washingMachines.status'));

        $response->assertOk();
        $response->assertJson([
            ['id' => $machine->id, 'name' => 'M1', 'busy' => false, 'currentOrderNumber' => null],
        ]);
    }

    public function test_status_reports_a_machine_currently_washing_as_busy(): void
    {
        $machine = WashingMachine::create(['name' => 'M1', 'is_active' => true]);

        $order = Order::create([
            'order_number' => 'WMS-1',
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);
        $order->refresh();
        // The status-transition guard trigger only allows one stage at a
        // time -- received -> sorting -> washing, not a direct jump.
        DB::statement("UPDATE orders SET status = 'sorting' WHERE id = {$order->id}");
        DB::statement("UPDATE orders SET status = 'washing', washing_machine_id = {$machine->id} WHERE id = {$order->id}");

        $response = $this->getJson(route('washingMachines.status'));

        $response->assertOk();
        $response->assertJson([
            ['id' => $machine->id, 'name' => 'M1', 'busy' => true, 'currentOrderNumber' => 'WMS-1'],
        ]);
    }

    public function test_status_omits_a_retired_machine(): void
    {
        WashingMachine::create(['name' => 'Retired', 'is_active' => false]);

        $response = $this->getJson(route('washingMachines.status'));

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_the_order_page_renders_the_machine_picker_when_next_stage_is_washing(): void
    {
        WashingMachine::create(['name' => 'M1', 'is_active' => true]);

        $order = Order::create([
            'order_number' => 'WMS-2',
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);
        $order->refresh();
        DB::statement("UPDATE orders SET status = 'sorting' WHERE id = {$order->id}");

        $response = $this->get(route('orders.show', $order));

        $response->assertOk();
        $response->assertSee('Select Washing Machine');
    }

    public function test_status_endpoint_requires_orders_manage_permission(): void
    {
        // No role assigned at all -- both seeded roles (Admin, Laundry) carry
        // orders.manage, so a roleless user is the only way to lack it here.
        $noRole = User::factory()->create();
        $this->actingAs($noRole->fresh());

        $this->getJson(route('washingMachines.status'))->assertForbidden();
    }
}
