<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\DamageType;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
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
        $assignmentEnabled = Setting::get('order.assignment_enabled', 'false') === 'true';

        // Everyone without orders.assign sees only their own assigned orders
        // once this is on, full stop -- no toggle, no opt-out. Deliberately
        // not orders.manage: that's needed just to process an order (advance,
        // cancel, record a payment) and plenty of legitimate floor staff
        // have it without needing to see everyone else's queue too.
        $scopedToSelf = $assignmentEnabled && ! auth()->user()->can('orders.assign');

        // collection.subscriptionCycle -- combinedPaymentStatus() and
        // balanceDue() both walk into the cycle for a subscription order;
        // without this the relation itself lazy-loads fresh per row (its
        // amountPaid()/balanceDue() are deliberately always-fresh queries
        // either way, eager-loading payments wouldn't skip those).
        $orders = Order::with(['customer', 'payments', 'packageLines.laundryPackage', 'receipt', 'assignedTo', 'collection.subscriptionCycle'])
            ->when($scopedToSelf, fn ($q) => $q->where('assigned_to', auth()->id()))
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

        // orders.view, not orders.manage -- the assignable pool has to
        // include view-only staff, since they're exactly who the "only see
        // what's assigned to me" scoping above is for. Restricting this to
        // orders.manage would make that scoping pointless: a view-only
        // person could never be assigned anything to see in the first place.
        $assignableStaff = $assignmentEnabled
            ? User::where('is_active', true)->permission('orders.view')->orderBy('name')->get()
            : collect();

        return view('orders.index', compact('orders', 'assignmentEnabled', 'assignableStaff', 'scopedToSelf'));
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer') ?: null;
        $forceWalkIn = $request->get('mode') === 'walk_in';

        return view('orders.create', compact('customerId', 'forceWalkIn'));
    }

    public function show(Order $order): View
    {
        $assignmentEnabled = Setting::get('order.assignment_enabled', 'false') === 'true';

        // Mirrors index()'s scoping -- without this, a scoped staff member
        // could just bookmark/guess a URL to see an order hidden from their
        // own list.
        if ($assignmentEnabled && ! auth()->user()->can('orders.assign') && $order->assigned_to !== auth()->id()) {
            abort(403, 'This order is not assigned to you.');
        }

        $order->load(['customer', 'packageLines.clothesLines', 'payments', 'statusHistory.order', 'statusHistory.changedBy', 'damageRecords', 'receipt', 'creator', 'washingMachine', 'assignedTo']);

        $damageTypes = DamageType::orderBy('name')->get();
        $washingMachines = WashingMachine::where('is_active', true)->orderBy('name')->get();

        return view('orders.show', compact('order', 'damageTypes', 'washingMachines', 'assignmentEnabled'));
    }

    /**
     * Standalone printable view (no app chrome) -- every load counts as a
     * print, this route's only reason to exist. reprint_count starts at 0 at
     * checkout, so the first open here already makes it 1.
     */
    public function receipt(Order $order): View
    {
        $order->load([
            'customer',
            'packageLines.clothesLines',
            'payments',
            'receipt',
            'collection.subscriptionCycle.payments',
            'collection.subscription.subscriptionPackage',
        ]);

        if ($order->receipt) {
            $order->receipt->increment('reprint_count');
        }

        return view('orders.receipt', compact('order'));
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

    /**
     * Purely a label -- see Order::assignedTo()'s doc comment. Assignable
     * staff need orders.view (same list the Orders index dropdown offers),
     * not orders.manage -- view-only staff are exactly who the "only see
     * what's assigned to me" scoping targets, so they have to be assignable
     * too, not just the managers doing the assigning.
     */
    public function assign(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'exists:users,id'],
        ]);

        if ($validated['assigned_to'] ?? null) {
            $staff = User::find($validated['assigned_to']);

            if (! $staff->can('orders.view')) {
                return back()->withErrors(['assigned_to' => 'This user does not have order-view permission.']);
            }
        }

        $order->update(['assigned_to' => $validated['assigned_to'] ?? null]);

        return back()->with('status', $validated['assigned_to'] ?? null
            ? "Order {$order->order_number} assigned."
            : "Order {$order->order_number} unassigned.");
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
