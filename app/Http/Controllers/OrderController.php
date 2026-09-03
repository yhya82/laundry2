<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusChanged;
use App\Models\DamageType;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WashingMachine;
use App\Services\NotificationDispatcher;
use App\Support\OrderPaymentRecorder;
use App\Support\SubscriptionCyclePaymentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(protected NotificationDispatcher $notifications)
    {
    }

    /**
     * Same boundary as index()'s scoping, but for any action that reaches an
     * order directly by ID -- view, advance, cancel, or pay. Without this, a
     * scoped-out staff member who can't see an unassigned order in their list
     * could still act on it by guessing/bookmarking the URL.
     */
    protected function ensureOrderAccessible(Order $order): void
    {
        $assignmentEnabled = Setting::get('order.assignment_enabled', 'false') === 'true';

        if ($assignmentEnabled && ! auth()->user()->can('orders.assign') && $order->assigned_to !== auth()->id()) {
            abort(403, 'This order is not assigned to you.');
        }
    }

    /**
     * The actual listing (search, filter, scoping, pagination) lives in the
     * orders-index Livewire component now, so this only renders the page
     * shell -- see that component for the query and permission scoping this
     * used to do here.
     */
    public function index(): View
    {
        return view('orders.index');
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

        $this->ensureOrderAccessible($order);

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
        $this->ensureOrderAccessible($order);

        $next = $order->nextStatus();

        if ($next === null) {
            return back()->withErrors(['status' => 'This order has no further stage to advance to.']);
        }

        if ($next === 'collection') {
            return $this->advanceToCollection($request, $order);
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
     * Records who physically collected the order and, optionally in the same
     * submit, a payment against whatever's still owed -- both atomically (one
     * DB::transaction), so a payment failure never leaves the order marked
     * collected without the money actually recorded. Reuses
     * OrderPaymentRecorder/SubscriptionCyclePaymentRecorder rather than
     * duplicating PaymentController's store-credit/overshoot/trigger-race
     * handling. The payment (if any) goes to the order's own balance if it
     * has one (e.g. a cycle-overage charge sitting on the order itself),
     * otherwise the subscription cycle's -- a subscription visit's own
     * order subtotal is normally 0, the flat fee lives on the cycle instead.
     */
    protected function advanceToCollection(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'collected_by_type' => ['required', 'in:customer,other'],
            'collected_by_name' => ['required_if:collected_by_type,other', 'nullable', 'string', 'max:255'],
            'collected_by_phone' => ['required_if:collected_by_type,other', 'nullable', 'string', 'regex:/^[+0-9][0-9 ()\-]{6,19}$/'],
            'credit_applied' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'method' => [$request->float('amount') > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ]);

        $hasPayment = $request->float('amount') > 0 || $request->float('credit_applied') > 0;
        $cycle = $order->subscriptionCycle();
        $from = $order->status;

        try {
            DB::transaction(function () use ($order, $validated, $hasPayment, $cycle) {
                $order->collected_by_type = $validated['collected_by_type'];
                $order->collected_by_name = $validated['collected_by_type'] === 'other' ? $validated['collected_by_name'] : null;
                $order->collected_by_phone = $validated['collected_by_type'] === 'other' ? $validated['collected_by_phone'] : null;
                $order->status = 'collection';
                $order->save();

                if ($hasPayment) {
                    if ($order->balanceDue() > 0) {
                        app(OrderPaymentRecorder::class)->record($order, $validated);
                    } elseif ($cycle) {
                        app(SubscriptionCyclePaymentRecorder::class)->record($cycle, $validated);
                    } else {
                        throw new \RuntimeException('This order is already fully paid.');
                    }
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        OrderStatusChanged::dispatch($order, $from);

        return back()->with('status', 'Order collected.');
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
        $this->ensureOrderAccessible($order);

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
