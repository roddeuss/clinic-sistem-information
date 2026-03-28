<?php

namespace App\Modules\Procedures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcedureMasterUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('procedure-masters.store') => $this->canAccess('create procedure management'),
            $this->routeIs('procedure-masters.update') => $this->canAccess('edit procedure management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'performer_scope' => ['required', 'in:doctor_only,nurse_only,both'],
            'requires_doctor_order' => ['required', 'boolean'],
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
            'requires_doctor_order' => $this->boolean('requires_doctor_order'),
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
