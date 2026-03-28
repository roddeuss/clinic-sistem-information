<?php

namespace App\Modules\DoctorLetters\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorLetterUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('doctor-letters.store') => $this->canAccess('create doctor letter management'),
            $this->routeIs('doctor-letters.update') => $this->canAccess('edit doctor letter management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'letter_type' => ['required', Rule::in(['sick_note', 'fit_note', 'control_note', 'drug_free_note'])],
            'issue_date' => ['nullable', 'date'],
            'diagnosis_summary' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sick_start_date' => ['nullable', 'date'],
            'sick_end_date' => ['nullable', 'date'],
            'healthy_statement' => ['nullable', 'string', 'max:2000'],
            'control_date' => ['nullable', 'date'],
            'control_notes' => ['nullable', 'string', 'max:2000'],
            'drug_test_date' => ['nullable', 'date'],
            'drug_test_method' => ['nullable', 'string', 'max:120'],
            'drug_test_result' => ['nullable', 'string', 'max:120'],
            'drug_free_statement' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'issue_date' => filled($this->input('issue_date')) ? (string) $this->input('issue_date') : null,
            'diagnosis_summary' => filled($this->input('diagnosis_summary')) ? trim((string) $this->input('diagnosis_summary')) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'sick_start_date' => filled($this->input('sick_start_date')) ? (string) $this->input('sick_start_date') : null,
            'sick_end_date' => filled($this->input('sick_end_date')) ? (string) $this->input('sick_end_date') : null,
            'healthy_statement' => filled($this->input('healthy_statement')) ? trim((string) $this->input('healthy_statement')) : null,
            'control_date' => filled($this->input('control_date')) ? (string) $this->input('control_date') : null,
            'control_notes' => filled($this->input('control_notes')) ? trim((string) $this->input('control_notes')) : null,
            'drug_test_date' => filled($this->input('drug_test_date')) ? (string) $this->input('drug_test_date') : null,
            'drug_test_method' => filled($this->input('drug_test_method')) ? trim((string) $this->input('drug_test_method')) : null,
            'drug_test_result' => filled($this->input('drug_test_result')) ? trim((string) $this->input('drug_test_result')) : null,
            'drug_free_statement' => filled($this->input('drug_free_statement')) ? trim((string) $this->input('drug_free_statement')) : null,
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
