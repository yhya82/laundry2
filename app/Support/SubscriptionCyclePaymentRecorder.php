<?php

namespace App\Support;

use App\Events\PaymentReceived;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SubscriptionCycle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class SubscriptionCyclePaymentRecorder
{
    /**
     * Extracted from PaymentController::recordForCycle() -- same reasoning
     * as OrderPaymentRecorder, so OrderController::advanceToCollection() can
     * settle a subscription order's cycle balance (where the flat monthly
     * fee actually lives, not on the order itself) without a second copy of
     * this logic.
     *
     * @throws \RuntimeException with a user-facing message on any rejection
     */
    public function record(SubscriptionCycle $subscriptionCycle, array $input): Payment
    {
        $balanceDue = $subscriptionCycle->balanceDue();

        if ($balanceDue <= 0) {
            throw new \RuntimeException('This cycle is already fully paid.');
        }

        $customer = $subscriptionCycle->subscription->customer;

        $creditApplied = Setting::get('payment.store_credit_enabled', 'true') === 'true'
            ? min($input['credit_applied'] ?? 0, $customer->store_credit_balance, $balanceDue)
            : 0;

        $totalCovered = round($creditApplied + $input['amount'], 2);

        if ($totalCovered <= 0) {
            throw new \RuntimeException('Enter an amount or apply store credit.');
        }

        if ($totalCovered > $balanceDue) {
            throw new \RuntimeException('This would exceed the remaining balance of GMD '.number_format($balanceDue, 2).'.');
        }

        try {
            $payment = DB::transaction(function () use ($subscriptionCycle, $customer, $creditApplied, $totalCovered, $input) {
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
