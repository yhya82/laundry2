<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
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

            // A subscription must have between 1 and 4 collections per month.
            'collections_per_month' => [
                'required',
                'integer',
                'min:1',
                'max:4',
            ],

            'collection_type' => [
                'required',
                'in:scheduled,non_scheduled',
            ],

            'max_clothes_per_cycle' => [
                'required',
                'integer',
                'min:1',
            ],
        ];
    }

    /**
     * Mirrors the relevant database guards at the form layer.
     * The database trigger remains the source of truth for preventing
     * multiple active subscriptions for the same customer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('customer_id')) {
                return;
            }

            $customer = Customer::find($this->input('customer_id'));

            if (! $customer) {
                return;
            }

            if ($customer->customer_type !== 'subscription') {
                $validator->errors()->add(
                    'customer_id',
                    'This customer is not set to Customer type: Subscription. Edit their profile first.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'collections_per_month.min' =>
                'A subscription must have at least 1 collection per month.',

            'collections_per_month.max' =>
                'A subscription cannot have more than 4 collections per month.',
        ];
    }

}