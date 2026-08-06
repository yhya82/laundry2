<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Distinct from PaymentReceived -- that one is for the Dashboard's revenue
 * ticker on new payments; this one is for the Payments index's status pill,
 * fired whenever a payment's status itself changes (i.e. a refund).
 */
class PaymentStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $paymentId;

    public string $toStatus;

    public function __construct(Payment $payment)
    {
        $this->paymentId = $payment->id;
        $this->toStatus = $payment->status;
    }

    public function broadcastOn(): array
    {
        return [new Channel('payments')];
    }

    public function broadcastAs(): string
    {
        return 'payment.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'paymentId' => $this->paymentId,
            'toStatus' => $this->toStatus,
        ];
    }
}
