<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ];
    }

    /**
     * Mirrors the DB trigger (trg_subscriptions_customer_type_guard) at the
     * form layer -- same principle as StoreCustomerRequest's phone check.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('customer_id')) {
                return;
            }

            $customer = Customer::find($this->input('customer_id'));

            if ($customer && $customer->customer_type !== 'subscription') {
                $validator->errors()->add('customer_id', 'This customer is not set to Customer type: Subscription. Edit their profile first.');
            }
        });
    }
}
