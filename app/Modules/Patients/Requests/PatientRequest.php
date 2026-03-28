<?php

namespace App\Modules\Patients\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('patients.store') => $this->canAccess('create patient management'),
            $this->routeIs('patients.update') => $this->canAccess('edit patient management'),
            $this->routeIs('patients.archive') => $this->canAccess('delete patient management'),
            default => false,
        };
    }

    public function rules(): array
    {
        $patientId = $this->route('patient')?->id;

        return match (true) {
            $this->routeIs('patients.store', 'patients.update') => [
                'branch_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('branches', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
                'full_name' => ['required', 'string', 'max:150'],
                'gender' => ['required', Rule::in(['male', 'female'])],
                'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
                'nik' => ['nullable', 'string', 'max:30', Rule::unique('patients', 'nik')->ignore($patientId)],
                'phone' => ['required', 'string', 'max:30'],
                'email' => ['nullable', 'email', 'max:255'],
                'province_code' => ['nullable', 'string', 'max:20'],
                'province_name' => ['nullable', 'string', 'max:120'],
                'city_code' => ['nullable', 'string', 'max:20'],
                'city_name' => ['nullable', 'string', 'max:120'],
                'district_code' => ['nullable', 'string', 'max:20'],
                'district_name' => ['nullable', 'string', 'max:120'],
                'village_code' => ['nullable', 'string', 'max:20'],
                'village_name' => ['nullable', 'string', 'max:120'],
                'address_line' => ['nullable', 'string', 'max:1000'],
                'allergy_notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('patients.archive') => [
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
        if ($this->routeIs('patients.archive')) {
            $this->merge([
                'reason' => Str::squish((string) $this->input('reason')),
            ]);

            return;
        }

        $nullableFields = [
            'branch_id',
            'nik',
            'email',
            'province_code',
            'province_name',
            'city_code',
            'city_name',
            'district_code',
            'district_name',
            'village_code',
            'village_name',
            'address_line',
            'allergy_notes',
        ];

        $payload = ['is_active' => $this->boolean('is_active')];

        foreach ($nullableFields as $field) {
            $payload[$field] = filled($this->input($field)) ? trim((string) $this->input($field)) : null;
        }

        $payload['full_name'] = Str::squish((string) $this->input('full_name'));
        $payload['nik'] = filled($payload['nik']) ? preg_replace('/\s+/', '', (string) $payload['nik']) : null;
        $payload['phone'] = preg_replace('/\s+/', '', (string) $this->input('phone'));
        $payload['email'] = filled($payload['email']) ? Str::lower((string) $payload['email']) : null;

        $this->merge($payload);
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
