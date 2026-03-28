<?php

namespace App\Modules\Navigation\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NavigationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'menu-categories.store',
                'menu-categories.update',
                'menu-categories.delete',
                'menu-items.store',
                'menu-items.update',
                'menu-items.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('menu-categories.store', 'menu-categories.update') => [
                'name' => ['required', 'string', 'max:100'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('menu-items.store', 'menu-items.update') => [
                'menu_category_id' => ['required', 'integer', Rule::exists('menu_categories', 'id')],
                'parent_id' => ['nullable', 'integer', Rule::exists('menus', 'id')],
                'title' => ['required', 'string', 'max:100'],
                'route_name' => ['nullable', 'string', 'max:255'],
                'icon' => ['required', 'string', 'max:100'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs(
            'menu-categories.store',
            'menu-categories.update',
            'menu-items.store',
            'menu-items.update',
        )) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
                'parent_id' => $this->input('parent_id') ?: null,
                'route_name' => $this->input('route_name') ?: null,
                'icon' => $this->input('icon') ?: 'box',
            ]);
        }
    }
}
