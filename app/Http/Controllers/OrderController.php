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

        $order->load(['customer', 'packageLines.clothesLines', 'payments', 'statusHistory.order', 'statusHistory.changedBy', 'damageRecords.damageType', 'damageRecords.resolution', 'receipt', 'creator', 'washingMachine', 'assignedTo']);

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

        if ($next === 'wash') {
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
     * Records who physically collected the order and, in the same submit, a
     * payment against whatever's still owed -- both atomically (one
     * DB::transaction), so a payment failure never leaves the order marked
     * collected without the money actually recorded. Reuses
     * OrderPaymentRecorder/SubscriptionCyclePaymentRecorder rather than
     * duplicating PaymentController's store-credit/overshoot/trigger-race
     * handling.
     *
     * What must be paid to collect:
     *  - the order's own balance, always (a walk-in total, or a
     *    subscription visit's overage charge);
     *  - PLUS the remaining cycle fee on the LAST collection of a cycle.
     * Anything up to order + cycle may be paid; one payment is split
     * automatically, order first, then the cycle. Same rules as
     * $collectionPayableDue in orders/show.blade.php.
     */

    
    protected function advanceToCollection(Request $request,Order $order): RedirectResponse {
        
    $cycle = $order->subscriptionCycle();
    $collection = $order->collection;

    $isSubscription = $order->order_source === 'subscription';

    $orderDue = $order->balanceDue();
    $cycleDue = $cycle?->balanceDue() ?? 0;

    $isFinalSubscriptionCollection = $isSubscription
        && $cycle !== null
        && $collection !== null
        && $cycle->isLastScheduledCollection($collection);

    /*
    |--------------------------------------------------------------------------
    | Determine the mandatory payment
    |--------------------------------------------------------------------------
    |
    | The order's own balance (a walk-in total, or a subscription visit's
    | overage charge) must always be settled before pickup. On the LAST
    | collection of a cycle the remaining cycle fee is required as well --
    | BOTH together, not one or the other (previously an order with its own
    | charge skipped the cycle fee entirely on the last visit).
    |
    */

    $payableDue = round($orderDue + ($isFinalSubscriptionCollection ? $cycleDue : 0), 2);

    // Most that can be paid here: the order's balance plus whatever the
    // cycle still owes (paying the cycle early on a non-final visit is fine).
    $maxPayable = round($orderDue + $cycleDue, 2);

    /*
    |--------------------------------------------------------------------------
    | Pay now / Pay later
    |--------------------------------------------------------------------------
    |
    | The Record Collection panel offers Pay later only when no payment is
    | required ($payableDue == 0) -- a non-final subscription visit with
    | the cycle fee still open. It's refused here whatever the browser
    | sends: a walk-in balance, an order's own extra charge, and the last
    | visit of a cycle must all be paid in full. Pay later records no
    | payment, so any amount sent with it is dropped. A request without
    | payment_timing (older clients) keeps the previous behaviour.
    |
    */

    $paymentTiming = $request->input('payment_timing');

    $request->validate([
        'payment_timing' => ['nullable', 'in:now,later'],
    ]);

    if ($paymentTiming === 'later') {
        if ($payableDue > 0) {
            return back()
                ->withInput()
                ->withErrors([
                    'payment_timing' => $isFinalSubscriptionCollection
                        ? 'This is the last collection of the cycle, so everything still owed (GMD '.number_format($payableDue, 2).') must be paid in full.'
                        : 'This order\'s balance must be paid in full to collect it.',
                ]);
        }

        $request->merge([
            'amount' => null,
            'credit_applied' => null,
            'method' => null,
            'method_note' => null,
        ]);
    }

    // Where paying is optional, choosing Pay now means a payment is
    // expected -- otherwise staff should have picked Pay later. (Where it's
    // required, the full-balance rule below already covers this.)
    $optionalDue = $payableDue <= 0 && $order->status !== 'cancelled' ? $cycleDue : 0;

    if ($paymentTiming === 'now' && $optionalDue > 0 && $request->float('amount') <= 0 && $request->float('credit_applied') <= 0) {
        return back()
            ->withInput()
            ->withErrors(['amount' => 'Enter the amount collected, or choose Pay later.']);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate the request
    |--------------------------------------------------------------------------
    */

    $validated = $request->validate([
        'collected_by_type' => [
            'required',
            'in:customer,other',
        ],

        'collected_by_name' => [
            'required_if:collected_by_type,other',
            'nullable',
            'string',
            'max:255',
        ],

        'collected_by_phone' => [
            'required_if:collected_by_type,other',
            'nullable',
            'string',
            'regex:/^[+0-9][0-9 ()\-]{6,19}$/',
        ],

        // Checked up front: the payment recorders would otherwise quietly
        // cap credit at the customer's balance, leaving a last collection
        // short of what it must cover.
        'credit_applied' => [
            'nullable',
            'numeric',
            'min:0',
            'max:'.(float) $order->customer->store_credit_balance,
        ],

        'amount' => [
            $payableDue > 0 ? 'required' : 'nullable',
            'numeric',
            'min:0',

            function ($attribute, $value, $fail) use (
                $request,
                $payableDue
            ) {
                if ($payableDue <= 0) {
                    return;
                }

                $totalCovered = round(
                    $request->float('credit_applied')
                        + (float) $value,
                    2
                );

                if ($totalCovered < $payableDue) {
                    $fail(
                        'The full balance due (GMD '
                        . number_format($payableDue, 2)
                        . ') must be paid to record collection.'
                    );
                }
            },
        ],

        'method' => [
            $request->float('amount') > 0
                ? 'required'
                : 'nullable',
            'in:cash,wave,aps,other',
        ],

        'method_note' => [
            'required_if:method,other',
            'nullable',
            'string',
            'max:255',
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | Determine whether a payment was submitted
    |--------------------------------------------------------------------------
    */

    $amount = (float) ($validated['amount'] ?? 0);

    $creditApplied = (float) (
        $validated['credit_applied'] ?? 0
    );

    $hasPayment = $amount > 0 || $creditApplied > 0;

    $from = $order->status;

    /*
    |--------------------------------------------------------------------------
    | Record payment and collect the order
    |--------------------------------------------------------------------------
    */

    try {
        DB::transaction(function () use (
            $order,
            $validated,
            $hasPayment,
            $cycle,
            $isFinalSubscriptionCollection,
            $orderDue,
            $payableDue,
            $maxPayable,
            $amount,
            $creditApplied
        ) {
            /*
            |--------------------------------------------------------------------------
            | Validate payment amount against the appropriate balance
            |--------------------------------------------------------------------------
            */

            if ($hasPayment) {
                $paymentTotal = round($amount + $creditApplied, 2);

                if ($maxPayable <= 0) {
                    throw new \RuntimeException('There is no outstanding balance to pay.');
                }

                // Never more than the order plus the cycle still owe.
                if ($paymentTotal > $maxPayable) {
                    throw new \RuntimeException(
                        'The payment cannot exceed the outstanding balance of GMD '.number_format($maxPayable, 2).'.'
                    );
                }

                // The last collection needs everything: order AND cycle.
                if ($isFinalSubscriptionCollection && $paymentTotal < $payableDue) {
                    throw new \RuntimeException(
                        'This is the last collection of the cycle, so everything still owed (GMD '.number_format($payableDue, 2).') must be paid in full.'
                    );
                }

                /*
                 * One payment, split automatically: the order's own balance
                 * first, the rest to the cycle. Store credit is used up
                 * first on each part, the same order the Terminal uses.
                 * Each part goes through its own recorder (store-credit
                 * ledger, cap checks, PaymentReceived), all inside this
                 * transaction -- any failure rolls the whole collection back.
                 */
                $orderCredit = min($creditApplied, $orderDue);
                $orderCash = min($amount, round($orderDue - $orderCredit, 2));
                $cycleCredit = round($creditApplied - $orderCredit, 2);
                $cycleCash = round($amount - $orderCash, 2);

                $base = [
                    'method' => $validated['method'] ?? null,
                    'method_note' => $validated['method_note'] ?? null,
                ];

                if ($orderCredit + $orderCash > 0) {
                    app(OrderPaymentRecorder::class)->record($order, [
                        ...$base,
                        'credit_applied' => $orderCredit,
                        'amount' => $orderCash,
                    ]);
                }

                if ($cycleCredit + $cycleCash > 0) {
                    if (! $cycle) {
                        throw new \RuntimeException('The payment cannot exceed the outstanding order balance.');
                    }

                    app(SubscriptionCyclePaymentRecorder::class)->record($cycle, [
                        ...$base,
                        'credit_applied' => $cycleCredit,
                        'amount' => $cycleCash,
                    ]);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Record who collected the order
            |--------------------------------------------------------------------------
            */

            $order->collected_by_type =
                $validated['collected_by_type'];

            $order->collected_by_name =
                $validated['collected_by_type'] === 'other'
                    ? $validated['collected_by_name']
                    : null;

            $order->collected_by_phone =
                $validated['collected_by_type'] === 'other'
                    ? $validated['collected_by_phone']
                    : null;

            /*
            |--------------------------------------------------------------------------
            | Mark the order as collected
            |--------------------------------------------------------------------------
            */

            $order->status = 'collection';

            $order->save();
        });
    } catch (\RuntimeException $e) {
        return back()
            ->withInput()
            ->withErrors([
                'amount' => $e->getMessage(),
            ]);
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
