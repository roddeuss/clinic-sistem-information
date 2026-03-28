<?php

namespace App\Modules\Counters\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CounterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('counters.store', 'counters.update', 'counters.delete') => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('counters.store', 'counters.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'name' => ['required', 'string', 'max:120'],
                'code' => ['nullable', 'string', 'max:40'],
                'location' => ['nullable', 'string', 'max:160'],
                'description' => ['nullable', 'string', 'max:1000'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('counters.store', 'counters.update')) {
            $this->merge([
                'code' => $this->input('code') ? strtoupper(trim((string) $this->input('code'))) : null,
                'location' => $this->input('location') ?: null,
                'description' => $this->input('description') ?: null,
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
