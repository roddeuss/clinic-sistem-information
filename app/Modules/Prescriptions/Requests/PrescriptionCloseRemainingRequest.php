<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionCloseRemainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit prescription management');
    }

    public function rules(): array
    {
        return [
            'closure_status' => ['required', Rule::in(['external', 'cancelled'])],
            'closure_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'closure_reason' => filled($this->input('closure_reason')) ? trim((string) $this->input('closure_reason')) : null,
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
