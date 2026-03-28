<?php

namespace App\Modules\Referrals\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferralUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('referrals.store') => $this->canAccess('create referral management'),
            $this->routeIs('referrals.update') => $this->canAccess('edit referral management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'referral_destination_id' => ['required', 'integer', Rule::exists('referral_destinations', 'id')],
            'diagnosis_summary' => ['nullable', 'string', 'max:5000'],
            'clinical_summary' => ['nullable', 'string', 'max:5000'],
            'treatment_summary' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'diagnosis_summary' => filled($this->input('diagnosis_summary')) ? trim((string) $this->input('diagnosis_summary')) : null,
            'clinical_summary' => filled($this->input('clinical_summary')) ? trim((string) $this->input('clinical_summary')) : null,
            'treatment_summary' => filled($this->input('treatment_summary')) ? trim((string) $this->input('treatment_summary')) : null,
            'reason' => trim((string) $this->input('reason')),
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
