<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['phone' => PhoneNumber::normalize($this->input('phone'))]);
    }

    /**
     * Mirrors the DB-level guarantees (customers.phone unique + CHECK
     * chk_customers_phone_format, customer_type enum) at the form layer so
     * users see a clear message instead of a raw DB error.
     */
    public function rules(): array
    {
        $customerId = $this->route('customer')?->id;

        return [
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => [
                'required',
                'string',
                'regex:/^\+220[0-9]{9}$/',
                Rule::unique('customers', 'phone')->ignore($customerId),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'customer_type' => ['required', Rule::in(['walk_in', 'subscription'])],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Enter a valid 9-digit phone number (e.g. 555123456).',
        ];
    }
}
