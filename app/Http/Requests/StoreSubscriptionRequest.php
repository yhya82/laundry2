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
            // Package fields are only the default shown on the create form --
            // these are what actually get stored, and can be overridden per
            // subscription before saving.
            'collections_per_month' => ['required', 'integer', 'min:1', 'max:28'],
            'collection_type' => ['required', 'in:scheduled,non_scheduled'],
            'max_clothes_per_cycle' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Mirrors the DB triggers (trg_subscriptions_customer_type_guard,
     * trg_subscriptions_one_active_guard_insert) at the form layer -- same
     * principle as StoreCustomerRequest's phone check. The one-active-per-
     * customer check itself isn't duplicated here -- the trigger is the
     * sole source of truth for it (see store()'s try/catch), since an
     * app-level-only copy previously missed SubscriptionController::resume(),
     * which can also produce a second active subscription via UPDATE.
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
                $validator->errors()->add('customer_id', 'This customer is not set to Customer type: Subscription. Edit their profile first.');
            }
        });
    }
}
