<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrescriptionOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit prescription management');
    }

    public function rules(): array
    {
        return [
            'override_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'override_reason' => filled($this->input('override_reason')) ? trim((string) $this->input('override_reason')) : null,
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
