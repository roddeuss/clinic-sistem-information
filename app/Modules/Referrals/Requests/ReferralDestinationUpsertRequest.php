<?php

namespace App\Modules\Referrals\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferralDestinationUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('referral-destinations.store') => $this->canAccess('create referral management'),
            $this->routeIs('referral-destinations.update') => $this->canAccess('edit referral management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'destination_type' => ['required', Rule::in(['hospital', 'specialist', 'lab_radiology'])],
            'name' => ['required', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'address' => filled($this->input('address')) ? trim((string) $this->input('address')) : null,
            'contact_person' => filled($this->input('contact_person')) ? trim((string) $this->input('contact_person')) : null,
            'phone' => filled($this->input('phone')) ? trim((string) $this->input('phone')) : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'is_active' => $this->boolean('is_active'),
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
