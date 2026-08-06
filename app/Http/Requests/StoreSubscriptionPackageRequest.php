<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * collections_per_month/max_clothes_per_cycle aren't on either form --
     * fixed at creation to sensible defaults (see
     * SubscriptionPackageController::store()) and never touched by update(),
     * since individual subscriptions already override both freely of their
     * own (New Subscription, Renew). 'sometimes' rather than dropped
     * entirely just in case either is ever posted directly.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'clothes_allowance' => ['required', 'integer', 'min:1'],
            'collections_per_month' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'max_clothes_per_cycle' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
