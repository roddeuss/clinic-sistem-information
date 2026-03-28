<?php

namespace App\Modules\VisitRegistrations\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VisitRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('visit-registrations.store') => $this->canAccess('create visit registration'),
            $this->routeIs('visit-registrations.update') => $this->canAccess('edit visit registration'),
            $this->routeIs('visit-registrations.check-in') => $this->canAccess('edit visit registration'),
            $this->routeIs('visit-registrations.cancel') => $this->canAccess('delete visit registration'),
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('visit-registrations.store', 'visit-registrations.update') => [
                'patient_id' => ['required', 'integer', Rule::exists('patients', 'id')],
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
                'visit_date' => ['required', 'date'],
                'visit_type' => ['required', Rule::in(['same_day', 'booking', 'emergency'])],
                'doctor_schedule_id' => ['nullable', 'integer', Rule::exists('doctor_schedules', 'id')],
                'slot_start_time' => ['nullable', 'date_format:H:i:s'],
                'slot_end_time' => ['nullable', 'date_format:H:i:s', 'after:slot_start_time'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('visit-registrations.cancel') => [
                'reason' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };
    }

    public function payload(): array
    {
        return $this->validated();
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('visit-registrations.store', 'visit-registrations.update')) {
            $this->merge([
                'doctor_schedule_id' => $this->input('doctor_schedule_id') ?: null,
                'slot_start_time' => $this->input('slot_start_time') ?: null,
                'slot_end_time' => $this->input('slot_end_time') ?: null,
                'notes' => filled($this->input('notes')) ? Str::squish((string) $this->input('notes')) : null,
            ]);
        }

        if ($this->routeIs('visit-registrations.cancel')) {
            $this->merge([
                'reason' => filled($this->input('reason')) ? Str::squish((string) $this->input('reason')) : null,
            ]);
        }
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
