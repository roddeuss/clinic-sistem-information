<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('medical-records.store') => $this->canAccess('create medical record management'),
            $this->routeIs('medical-records.update') => $this->canAccess('edit medical record management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')],
            'subjective' => ['nullable', 'string', 'max:6000'],
            'objective' => ['nullable', 'string', 'max:6000'],
            'assessment' => ['nullable', 'string', 'max:6000'],
            'plan' => ['nullable', 'string', 'max:6000'],
            'diagnosis_notes' => ['nullable', 'string', 'max:2000'],
            'primary_icd10_id' => ['nullable', 'integer', Rule::exists('icd10_codes', 'id')],
            'secondary_icd10_ids' => ['nullable', 'array'],
            'secondary_icd10_ids.*' => ['integer', Rule::exists('icd10_codes', 'id')],
            'submit_action' => ['required', Rule::in(['draft', 'final'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $secondaryIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            (array) $this->input('secondary_icd10_ids', [])
        ))));

        $this->merge([
            'subjective' => filled($this->input('subjective')) ? trim((string) $this->input('subjective')) : null,
            'objective' => filled($this->input('objective')) ? trim((string) $this->input('objective')) : null,
            'assessment' => filled($this->input('assessment')) ? trim((string) $this->input('assessment')) : null,
            'plan' => filled($this->input('plan')) ? trim((string) $this->input('plan')) : null,
            'diagnosis_notes' => filled($this->input('diagnosis_notes')) ? trim((string) $this->input('diagnosis_notes')) : null,
            'secondary_icd10_ids' => $secondaryIds,
        ]);
    }

    public function payload(): array
    {
        $validated = $this->validated();

        return [
            ...$validated,
            'secondary_icd10_ids' => collect($validated['secondary_icd10_ids'] ?? [])
                ->reject(fn ($id) => (int) $id === (int) ($validated['primary_icd10_id'] ?? 0))
                ->values()
                ->all(),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
