<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\Order;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request): View
    {
        $orders = Order::with('customer')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->get('q').'%';
                $q->where('order_number', 'like', $term);
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(): View
    {
        return view('orders.create');
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'packageLines.clothesLines', 'payments', 'statusHistory.order', 'damageRecords', 'receipt', 'creator']);

        return view('orders.show', compact('order'));
    }

    /**
     * Only ever advances to the single next stage -- never accepts an
     * arbitrary target status from the request. trg_orders_status_history_log
     * writes the timeline row automatically; nothing here logs it.
     */
    public function advance(Order $order): RedirectResponse
    {
        $next = $order->nextStatus();

        if ($next === null) {
            return back()->withErrors(['status' => 'This order has no further stage to advance to.']);
        }

        $from = $order->status;
        $order->status = $next;
        $order->save();

        OrderStatusChanged::dispatch($order, $from);

        if ($next === 'completed') {
            $this->notifications->toCustomer(
                $order->customer,
                'sms',
                'Order ready',
                "Hi {$order->customer->full_name}, your order {$order->order_number} is complete and ready for pickup."
            );
        }

        return back()->with('status', "Order moved to " . ucfirst($next) . '.');
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        if ($order->isTerminal()) {
            return back()->withErrors(['status' => 'This order is already in a terminal state and cannot be cancelled.']);
        }

        $validated = $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:255'],
        ]);

        $from = $order->status;
        $order->cancellation_reason = $validated['cancellation_reason'];
        $order->status = 'cancelled';
        $order->save();

        OrderStatusChanged::dispatch($order, $from);

        return back()->with('status', 'Order cancelled.');
    }
}
