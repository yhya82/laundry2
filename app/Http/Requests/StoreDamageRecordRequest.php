<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDamageRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'damage_type_id' => ['required', 'exists:damage_types,id'],
            'item_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * Mirrors trg_damage_records_block_cancelled_order at the form layer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $order = $this->route('order');

            if ($order && $order->status === 'cancelled') {
                $validator->errors()->add('order', 'Cannot report damage against a cancelled order.');
            }
        });
    }
}
