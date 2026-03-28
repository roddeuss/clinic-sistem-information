<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('prescriptions.store') => $this->canAccess('create prescription management'),
            $this->routeIs('prescriptions.update') => $this->canAccess('edit prescription management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
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
