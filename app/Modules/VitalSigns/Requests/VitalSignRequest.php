<?php

namespace App\Modules\VitalSigns\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('vital-signs.store') => $this->canAccess('create vital sign management'),
            $this->routeIs('vital-signs.update') => $this->canAccess('edit vital sign management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'systolic_bp' => ['required', 'integer', 'between:50,300'],
            'diastolic_bp' => ['required', 'integer', 'between:30,200'],
            'temperature_celsius' => ['required', 'numeric', 'between:30,45'],
            'pulse_rate' => ['required', 'integer', 'between:20,250'],
            'respiratory_rate' => ['nullable', 'integer', 'between:5,80'],
            'weight_kg' => ['required', 'numeric', 'between:1,300'],
            'height_cm' => ['required', 'numeric', 'between:30,250'],
            'spo2_percent' => ['required', 'integer', 'between:50,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'respiratory_rate' => filled($this->input('respiratory_rate')) ? (int) $this->input('respiratory_rate') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'recorded_at' => filled($this->input('recorded_at')) ? $this->input('recorded_at') : null,
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
