<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Same field set as StoreSubscriptionRequest minus customer_id and
     * start_date. end_date isn't editable: a scheduled subscription's comes
     * from its schedule, and a non-scheduled one keeps whatever it had.
     *
     * collection_type is included in this form so subscription
     * settings can be updated in one place.
     */
    public function rules(): array
    {
        return [
            'subscription_package_id' => [
                'required',
                'exists:subscription_packages,id',
            ],

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
