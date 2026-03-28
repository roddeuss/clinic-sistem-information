<?php

namespace App\Modules\ProductCategories\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'product-categories.store',
                'product-categories.update',
                'product-categories.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('product-categories.store', 'product-categories.update') => [
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('product_categories', 'code')->ignore($this->route('productCategory')),
                ],
                'name' => ['required', 'string', 'max:160'],
                'description' => ['nullable', 'string', 'max:1000'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') ?: null,
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
