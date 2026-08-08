<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_status_follows_the_stage_sequence(): void
    {
        $cases = [
            'received' => 'sorting',
            'sorting' => 'washing',
            'washing' => 'drying',
            'drying' => 'ironing',
            'ironing' => 'packaging',
            'packaging' => 'completed',
        ];

        foreach ($cases as $current => $expectedNext) {
            // status is deliberately not mass-assignable (see the security
            // review) -- set directly, the same escape hatch the app itself
            // never uses outside a handful of trusted, server-only spots.
            $order = new Order();
            $order->status = $current;
            $this->assertSame($expectedNext, $order->nextStatus(), "Expected {$current} -> {$expectedNext}.");
        }
    }

    public function test_terminal_statuses_have_no_next_status(): void
    {
        $order = new Order();
        $order->status = 'completed';
        $this->assertNull($order->nextStatus());

        $order->status = 'cancelled';
        $this->assertNull($order->nextStatus());
    }

    public function test_is_terminal(): void
    {
        $order = new Order();

        $order->status = 'completed';
        $this->assertTrue($order->isTerminal());

        $order->status = 'cancelled';
        $this->assertTrue($order->isTerminal());

        $order->status = 'received';
        $this->assertFalse($order->isTerminal());

        $order->status = 'washing';
        $this->assertFalse($order->isTerminal());
    }

    public function test_balance_due_reflects_payments(): void
    {
        $order = Order::create([
            'order_number' => 'UT-1',
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);
        // total_amount is a DB-generated STORED column -- not populated on
        // the in-memory instance until refreshed, same reason submitOrder()
        // refreshes right after creating a real order.
        $order->refresh();

        $this->assertEquals(100.0, $order->balanceDue());
        $this->assertSame('unpaid', $order->paymentStatus());

        $order->payments()->create(['amount' => 40, 'method' => 'cash']);
        $order = Order::find($order->id); // fresh instance -- amountPaid() is memoized per-instance
        $this->assertEquals(60.0, $order->balanceDue());
        $this->assertSame('partial', $order->paymentStatus());

        $order->payments()->create(['amount' => 60, 'method' => 'cash']);
        $order = Order::find($order->id);
        $this->assertEquals(0.0, $order->balanceDue());
        $this->assertSame('paid', $order->paymentStatus());
    }

    public function test_a_cancelled_order_always_has_zero_balance_due_regardless_of_payments(): void
    {
        $order = Order::create([
            'order_number' => 'UT-2',
            'customer_id' => Customer::factory()->create()->id,
            'order_source' => 'walk_in',
            'subtotal' => 100,
        ]);

        // status isn't mass-assignable, so this goes in as the DB default
        // ('received') -- moved to 'cancelled' via raw SQL since the app
        // itself only ever does this through OrderController::cancel(),
        // already covered end-to-end in WorkflowsTest.
        \Illuminate\Support\Facades\DB::statement("UPDATE orders SET status = 'cancelled' WHERE id = {$order->id}");
        $order = Order::find($order->id);

        $this->assertEquals(0.0, $order->balanceDue(), 'A cancelled order should never show a balance due, even unpaid.');
        // amountPaid() still reports the real (zero) figure -- only balanceDue()'s interpretation changes.
        $this->assertSame('unpaid', $order->paymentStatus(), 'Nothing was ever paid, so it should read unpaid, not paid, once cancelled.');
    }
}
