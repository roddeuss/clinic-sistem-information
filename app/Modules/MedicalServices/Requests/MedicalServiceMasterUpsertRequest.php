<?php

namespace App\Modules\MedicalServices\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalServiceMasterUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('medical-services.store') => $this->canAccess('create medical service management'),
            $this->routeIs('medical-services.update') => $this->canAccess('edit medical service management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'service_type' => ['required', 'in:clinical,nursing,administrative,observation,other'],
            'description' => ['nullable', 'string', 'max:1000'],
            'default_fee' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'branch_prices' => ['nullable', 'array'],
            'branch_prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $prices = collect($this->input('branch_prices', []))
            ->mapWithKeys(fn ($value, $key): array => [
                (string) $key => filled($value) ? (float) $value : null,
            ])
            ->all();

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'default_fee' => (float) $this->input('default_fee'),
            'branch_prices' => $prices,
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
