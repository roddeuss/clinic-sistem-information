<?php

namespace App\Modules\Laboratory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoryTestUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('laboratory-tests.store') => $this->canAccess('create laboratory management'),
            $this->routeIs('laboratory-tests.update') => $this->canAccess('edit laboratory management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'diagnostic_category' => ['required', 'in:laboratory,radiology,other_support'],
            'sample_type' => ['nullable', 'string', 'max:80'],
            'default_provider_type' => ['required', 'in:internal,external'],
            'result_entry_mode' => ['required', 'in:structured,narrative,hybrid'],
            'description' => ['nullable', 'string', 'max:1000'],
            'parameter_lines' => ['nullable', 'string'],
            'internal_prices' => ['nullable', 'array'],
            'internal_prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'external_prices' => ['nullable', 'array'],
            'external_prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'diagnostic_category' => $this->input('diagnostic_category') ?: 'laboratory',
            'sample_type' => filled($this->input('sample_type')) ? trim((string) $this->input('sample_type')) : null,
            'default_provider_type' => $this->input('default_provider_type') ?: 'internal',
            'result_entry_mode' => $this->input('result_entry_mode') ?: 'structured',
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'parameter_lines' => filled($this->input('parameter_lines')) ? trim((string) $this->input('parameter_lines')) : null,
            'internal_prices' => collect($this->input('internal_prices', []))
                ->mapWithKeys(fn ($value, $key): array => [(string) $key => filled($value) ? (float) $value : null])
                ->all(),
            'external_prices' => collect($this->input('external_prices', []))
                ->mapWithKeys(fn ($value, $key): array => [(string) $key => filled($value) ? (float) $value : null])
                ->all(),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    public function payload(): array
    {
        return $this->validated();
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
