<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\DamageType;
use App\Models\Order;
use App\Models\WashingMachine;
use App\Services\NotificationDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    public function index(Request $request): View
    {
        $orders = Order::with(['customer', 'payments', 'packageLines.laundryPackage'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->get('q').'%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('order_number', 'like', $term)
                        ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', $term));
                });
            })
            ->orderByDesc(DB::raw("EXISTS (
                SELECT 1 FROM order_package_lines opl
                INNER JOIN laundry_packages lp ON lp.id = opl.laundry_package_id
                WHERE opl.order_id = orders.id AND lp.priority = 'high'
            )"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer') ?: null;
        $forceWalkIn = $request->get('mode') === 'walk_in';

        return view('orders.create', compact('customerId', 'forceWalkIn'));
    }

    public function show(Order $order): View
    {
        $order->load(['customer', 'packageLines.clothesLines', 'payments', 'statusHistory.order', 'statusHistory.changedBy', 'damageRecords', 'receipt', 'creator', 'washingMachine']);

        $damageTypes = DamageType::orderBy('name')->get();
        $washingMachines = WashingMachine::where('is_active', true)->orderBy('name')->get();

        return view('orders.show', compact('order', 'damageTypes', 'washingMachines'));
    }

    /**
     * Only ever advances to the single next stage -- never accepts an
     * arbitrary target status from the request. trg_orders_status_history_log
     * writes the timeline row automatically; nothing here logs it.
     */
    public function advance(Request $request, Order $order): RedirectResponse
    {
        $next = $order->nextStatus();

        if ($next === null) {
            return back()->withErrors(['status' => 'This order has no further stage to advance to.']);
        }

        $washingMachine = null;

        if ($next === 'washing') {
            $request->validate(['washing_machine_id' => ['required', 'exists:washing_machines,id']]);

            $washingMachine = WashingMachine::find($request->integer('washing_machine_id'));

            if (! $washingMachine->is_active) {
                return back()->withErrors(['status' => 'This washing machine is retired and cannot take new orders.']);
            }

            if ($washingMachine->isBusy()) {
                return back()->withErrors(['status' => "This washing machine is already washing order {$washingMachine->currentOrder()->order_number}."]);
            }
        }

        $from = $order->status;
        $order->status = $next;

        if ($washingMachine) {
            $order->washing_machine_id = $washingMachine->id;
        }

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
