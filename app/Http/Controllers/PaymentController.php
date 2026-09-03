<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionCycle;
use App\Support\OrderPaymentRecorder;
use App\Support\SubscriptionCyclePaymentRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $payments = Payment::with(['order.customer', 'subscription.customer'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->get('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('payments.index', compact('payments'));
    }

    /**
     * Closes (all or part of) an order's balance after the Terminal collected
     * less than the full total at drop-off. trg_payments_cap_guard is still
     * the real backstop against overshooting; the balanceDue() checks here
     * are just so staff see a clear message instead of a raw DB error.
     *
     * A subscription order's own balance is normally 0 -- its flat monthly
     * fee lives on the cycle instead -- so this settles the order's own
     * balance first if it has one (e.g. a cycle-overage charge), otherwise
     * falls back to the cycle, same preference OrderController::advanceToCollection()
     * uses. Without this, the order page's "Record Payment" would only ever
     * work for walk-in orders, even though it's the button shown here.
     */
    public function record(Request $request, Order $order, OrderPaymentRecorder $recorder, SubscriptionCyclePaymentRecorder $cycleRecorder): RedirectResponse
    {
        // Mirrors OrderController::ensureOrderAccessible() -- paying an order
        // is as much "acting on it" as advancing or cancelling it, so it
        // needs the same assignment boundary.
        $assignmentEnabled = Setting::get('order.assignment_enabled', 'false') === 'true';

        if ($assignmentEnabled && ! auth()->user()->can('orders.assign') && $order->assigned_to !== auth()->id()) {
            abort(403, 'This order is not assigned to you.');
        }

        $validated = $request->validate([
            'credit_applied' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => [$request->float('amount') > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ]);

        try {
            if ($order->balanceDue() > 0) {
                $recorder->record($order, $validated);
            } elseif ($cycle = $order->subscriptionCycle()) {
                $cycleRecorder->record($cycle, $validated);
            } else {
                throw new \RuntimeException('This order is already fully paid.');
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment recorded.');
    }

    /**
     * Same as record() above, but for a subscription cycle's flat price
     * directly -- it isn't attached to any one collection's order, since
     * every collection in the cycle shares the same balance.
     */
    public function recordForCycle(Request $request, SubscriptionCycle $subscriptionCycle, SubscriptionCyclePaymentRecorder $recorder): RedirectResponse
    {
        $validated = $request->validate([
            'credit_applied' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => [$request->float('amount') > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ]);

        try {
            $recorder->record($subscriptionCycle, $validated);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('status', 'Payment recorded.');
    }
}
