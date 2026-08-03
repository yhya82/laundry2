<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mirrors trg_refunds_cap_guard at the form layer, same principle as the
     * other Store*Request classes -- the trigger is still the real cap.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $payment = $this->route('payment');

            if ($payment && $this->filled('amount') && (float) $this->input('amount') > $payment->remainingRefundable()) {
                $validator->errors()->add('amount', 'Cannot refund more than GMD '.number_format($payment->remainingRefundable(), 2).' remaining on this payment.');
            }
        });
    }
}
