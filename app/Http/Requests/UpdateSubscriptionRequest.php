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
     * Same field set as StoreSubscriptionRequest minus customer_id (can't
     * reassign to a different customer via edit) and start_date --
     * subscriptions.start_date is the historical anchor of the FIRST cycle
     * only; once a subscription has renewed even once, the current cycle's
     * own starts_on has moved on and is no longer the same date, so there's
     * no single "start date" left to safely edit through this form. See
     * SubscriptionController::update()'s guard/reschedule handling.
     *
     * collection_type lives here now too -- this form replaces the old
     * standalone updateCollectionType() action/endpoint entirely, folding
     * it into the same single edit instead of two separate places to
     * change a subscription.
     */
    public function rules(): array
    {
        return [
            'subscription_package_id' => ['required', 'exists:subscription_packages,id'],
            'end_date' => ['nullable', 'date'],
            'collections_per_month' => ['required', 'integer', 'min:1', 'max:28'],
            'collection_type' => ['required', 'in:scheduled,non_scheduled'],
            'max_clothes_per_cycle' => ['required', 'integer', 'min:1'],
        ];
    }
}
