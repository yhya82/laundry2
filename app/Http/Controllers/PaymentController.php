<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRefundRequest;
use App\Models\Payment;
use Illuminate\Database\QueryException;
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

    public function refund(StoreRefundRequest $request, Payment $payment): RedirectResponse
    {
        try {
            $payment->refunds()->create([
                'amount' => $request->validated('amount'),
                'reason' => $request->validated('reason'),
                'refunded_by' => auth()->id(),
            ]);
        } catch (QueryException $e) {
            return back()->withErrors(['amount' => 'This refund could not be processed -- it may exceed what remains refundable.']);
        }

        return back()->with('status', 'Refund recorded.');
    }
}
