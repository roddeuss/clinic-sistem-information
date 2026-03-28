<?php

namespace App\Modules\Sections\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('sections.store', 'sections.update', 'sections.delete') => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('sections.store', 'sections.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'name' => ['required', 'string', 'max:120'],
                'type' => ['required', 'string', Rule::in(['regular', 'emergency'])],
                'queue_prefix' => ['nullable', 'string', 'max:10'],
                'queue_number_padding' => ['required', 'integer', 'min:2', 'max:6'],
                'allow_appointment' => ['required', 'boolean'],
                'allow_walk_in' => ['required', 'boolean'],
                'description' => ['nullable', 'string', 'max:1000'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('sections.store', 'sections.update')) {
            $this->merge([
                'queue_prefix' => $this->input('queue_prefix') ? strtoupper(trim((string) $this->input('queue_prefix'))) : null,
                'type' => $this->input('type') ?: 'regular',
                'allow_appointment' => $this->boolean('allow_appointment'),
                'allow_walk_in' => $this->boolean('allow_walk_in'),
                'is_active' => $this->boolean('is_active'),
                'description' => $this->input('description') ?: null,
            ]);
        }
    }
}
