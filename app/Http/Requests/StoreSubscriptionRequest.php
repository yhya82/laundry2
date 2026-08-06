<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Models\Setting;
use App\Models\Subscription;
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

            if (! $customer) {
                return;
            }

            if ($customer->customer_type !== 'subscription') {
                $validator->errors()->add('customer_id', 'This customer is not set to Customer type: Subscription. Edit their profile first.');

                return;
            }

            $maxPackages = (int) Setting::get('subscription.max_active_packages_per_customer', '1');

            if ($maxPackages > 0) {
                $activeCount = Subscription::where('customer_id', $customer->id)->where('status', 'active')->count();

                if ($activeCount >= $maxPackages) {
                    $validator->errors()->add('customer_id', "This customer already has the maximum of {$maxPackages} active package(s).");
                }
            }
        });
    }
}
