<?php

namespace App\Modules\VisitRegistrations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRegistrationAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view visit registration');
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
            'visit_date' => ['required', 'date'],
            'visit_type' => ['required', 'string', Rule::in(['same_day', 'booking', 'emergency'])],
            'registration_id' => ['nullable', 'integer', Rule::exists('visit_registrations', 'id')],
        ];
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
