<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDamageResolutionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_type' => ['required', Rule::in(['cash', 'store_credit', 'replacement'])],
            'amount' => [$this->input('resolution_type') !== 'replacement' ? 'required' : 'nullable', 'nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
