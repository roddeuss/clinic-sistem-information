<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrescriptionFinalizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit prescription management');
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
