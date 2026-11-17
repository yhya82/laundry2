<?php

namespace App\Http\Requests;

use App\Rules\UniquePhone;
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
            // PhoneNumber rules: 7-digit numbers were converted to 9 digits by
            // normalize() above; the old 7-digit form counts as the same line.
            'phone' => [
                'required',
                'string',
                'regex:'.PhoneNumber::VALID_PATTERN,
                new UniquePhone('customers', $customerId, 'A customer with this phone number already exists.'),
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
            'phone.regex' => PhoneNumber::INVALID_MESSAGE,
        ];
    }
}
