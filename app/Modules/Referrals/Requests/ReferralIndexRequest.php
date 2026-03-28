<?php

namespace App\Modules\Referrals\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferralIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view referral management');
    }

    public function rules(): array
    {
        return [
            'destination_search' => ['nullable', 'string', 'max:100'],
            'destination_status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
            'destination_type' => ['nullable', 'string', Rule::in(['hospital', 'specialist', 'lab_radiology'])],
            'destination_sort_by' => ['nullable', 'string', Rule::in(['code', 'name', 'destination_type', 'created_at'])],
            'destination_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'destination_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'referral_search' => ['nullable', 'string', 'max:120'],
            'referral_status' => ['nullable', 'string', Rule::in(['draft', 'issued', 'voided'])],
            'referral_sort_by' => ['nullable', 'string', Rule::in(['referral_no', 'status', 'issued_at', 'created_at'])],
            'referral_sort_direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'referral_per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
        ];
    }

    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'destination_search' => trim((string) ($validated['destination_search'] ?? '')),
            'destination_status' => (string) ($validated['destination_status'] ?? ''),
            'destination_type' => (string) ($validated['destination_type'] ?? ''),
            'destination_sort_by' => (string) ($validated['destination_sort_by'] ?? 'name'),
            'destination_sort_direction' => (string) ($validated['destination_sort_direction'] ?? 'asc'),
            'destination_per_page' => (int) ($validated['destination_per_page'] ?? 10),
            'referral_search' => trim((string) ($validated['referral_search'] ?? '')),
            'referral_status' => (string) ($validated['referral_status'] ?? ''),
            'referral_sort_by' => (string) ($validated['referral_sort_by'] ?? 'issued_at'),
            'referral_sort_direction' => (string) ($validated['referral_sort_direction'] ?? 'desc'),
            'referral_per_page' => (int) ($validated['referral_per_page'] ?? 10),
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
