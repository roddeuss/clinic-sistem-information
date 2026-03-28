<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrescriptionDispenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit prescription management');
    }

    public function rules(): array
    {
        return [
            'quantity_dispensed' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'unit_price' => filled($this->input('unit_price')) ? (float) $this->input('unit_price') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
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
