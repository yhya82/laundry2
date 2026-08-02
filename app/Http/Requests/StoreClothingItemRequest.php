<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClothingItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Only required when the category isn't already fixed by the route
            // (i.e. the standalone /catalog/items form with its own picker).
            'clothes_category_id' => [$this->route('category') ? 'exclude' : 'required', 'exists:clothes_categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
