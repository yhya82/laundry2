<?php

namespace App\Support;

use App\Events\PaymentReceived;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderPaymentRecorder
{
    /**
     * Extracted from PaymentController::record() so the same store-credit
     * application, overshoot checks, and trg_payments_cap_guard-race
     * backstop are shared with OrderController::advance()'s collection
     * step, instead of a second copy of this logic drifting out of sync.
     *
     * $input is the already-validated ['credit_applied' => ?, 'amount' =>,
     * 'method' => ?] shape both callers produce. Returns null (no error)
     * only on success; throws via the same friendly-message convention the
     * original method used, for the caller to turn into a redirect.
     *
     * @throws \RuntimeException with a user-facing message on any rejection
     */
    public function record(Order $order, array $input): Payment
    {
        $balanceDue = $order->balanceDue();

        if ($balanceDue <= 0) {
            throw new \RuntimeException('This order is already fully paid.');
        }

        $creditApplied = Setting::get('payment.store_credit_enabled', 'true') === 'true'
            ? min($input['credit_applied'] ?? 0, $order->customer->store_credit_balance, $balanceDue)
            : 0;

        $totalCovered = round($creditApplied + $input['amount'], 2);

        if ($totalCovered <= 0) {
            throw new \RuntimeException('Enter an amount or apply store credit.');
        }

        if ($totalCovered > $balanceDue) {
            throw new \RuntimeException('This would exceed the remaining balance of GMD '.number_format($balanceDue, 2).'.');
        }

        try {
            $payment = DB::transaction(function () use ($order, $creditApplied, $totalCovered, $input) {
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
                    'method' => $creditApplied >= $totalCovered ? 'store_credit' : $input['method'],
                    'received_by' => auth()->id(),
                ]);
            });
        } catch (QueryException $e) {
            throw new \RuntimeException('This payment could not be processed — refresh and try again.');
        }

        PaymentReceived::dispatch($payment);

        return $payment;
    }
}
