<?php

namespace App\Modules\DoctorLetters\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorLetterVoidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('delete doctor letter management');
    }

    public function rules(): array
    {
        return [
            'void_reason' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'void_reason' => trim((string) $this->input('void_reason')),
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
