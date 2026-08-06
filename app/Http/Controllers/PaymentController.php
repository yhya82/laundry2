<?php

namespace App\Http\Controllers;

use App\Events\PaymentReceived;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionCycle;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
     */
    public function record(Request $request, Order $order): RedirectResponse
    {
        $balanceDue = $order->balanceDue();

        if ($balanceDue <= 0) {
            return back()->withInput()->withErrors(['amount' => 'This order is already fully paid.']);
        }

        $validated = $request->validate([
            'credit_applied' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => [$request->float('amount') > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ]);

        $creditApplied = Setting::get('payment.store_credit_enabled', 'true') === 'true'
            ? min($validated['credit_applied'] ?? 0, $order->customer->store_credit_balance, $balanceDue)
            : 0;

        $totalCovered = round($creditApplied + $validated['amount'], 2);

        if ($totalCovered <= 0) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount or apply store credit.']);
        }

        if ($totalCovered > $balanceDue) {
            return back()->withInput()->withErrors(['amount' => 'This would exceed the remaining balance of GMD '.number_format($balanceDue, 2).'.']);
        }

        try {
            $payment = DB::transaction(function () use ($order, $creditApplied, $totalCovered, $validated) {
                if ($creditApplied > 0) {
                    $order->customer->creditTransactions()->create([
                        'type' => 'debit',
                        'amount' => $creditApplied,
                        'reference_type' => 'order',
                        'reference_id' => $order->id,
                        'created_by' => auth()->id(),
                    ]);
                }

                return $order->payments()->create([
                    'amount' => $totalCovered,
                    'credit_applied' => $creditApplied,
                    'method' => $creditApplied >= $totalCovered ? 'store_credit' : $validated['method'],
                    'received_by' => auth()->id(),
                ]);
            });
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['amount' => 'This payment could not be processed — refresh and try again.']);
        }

        PaymentReceived::dispatch($payment);

        return back()->with('status', 'Payment recorded.');
    }

    /**
     * Same as record() above, but for a subscription cycle's flat price
     * directly -- it isn't attached to any one collection's order, since
     * every collection in the cycle shares the same balance.
     */
    public function recordForCycle(Request $request, SubscriptionCycle $subscriptionCycle): RedirectResponse
    {
        $balanceDue = $subscriptionCycle->balanceDue();

        if ($balanceDue <= 0) {
            return back()->withInput()->withErrors(['amount' => 'This cycle is already fully paid.']);
        }

        $validated = $request->validate([
            'credit_applied' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => [$request->float('amount') > 0 ? 'required' : 'nullable', 'in:cash,card,mixed'],
        ]);

        $customer = $subscriptionCycle->subscription->customer;

        $creditApplied = Setting::get('payment.store_credit_enabled', 'true') === 'true'
            ? min($validated['credit_applied'] ?? 0, $customer->store_credit_balance, $balanceDue)
            : 0;

        $totalCovered = round($creditApplied + $validated['amount'], 2);

        if ($totalCovered <= 0) {
            return back()->withInput()->withErrors(['amount' => 'Enter an amount or apply store credit.']);
        }

        if ($totalCovered > $balanceDue) {
            return back()->withInput()->withErrors(['amount' => 'This would exceed the remaining balance of GMD '.number_format($balanceDue, 2).'.']);
        }

        try {
            $payment = DB::transaction(function () use ($subscriptionCycle, $customer, $creditApplied, $totalCovered, $validated) {
                if ($creditApplied > 0) {
                    $customer->creditTransactions()->create([
                        'type' => 'debit',
                        'amount' => $creditApplied,
                        'reference_type' => 'subscription_cycle',
                        'reference_id' => $subscriptionCycle->id,
                        'created_by' => auth()->id(),
                    ]);
                }

                return $subscriptionCycle->payments()->create([
                    'subscription_id' => $subscriptionCycle->subscription_id,
                    'amount' => $totalCovered,
                    'credit_applied' => $creditApplied,
                    'method' => $creditApplied >= $totalCovered ? 'store_credit' : $validated['method'],
                    'received_by' => auth()->id(),
                ]);
            });
        } catch (QueryException $e) {
            return back()->withInput()->withErrors(['amount' => 'This payment could not be processed — refresh and try again.']);
        }

        PaymentReceived::dispatch($payment);

        return back()->with('status', 'Payment recorded.');
    }
}
