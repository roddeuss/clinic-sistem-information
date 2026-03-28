<?php

namespace App\Modules\ClinicSettings\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClinicSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'clinic.profile',
                'clinic-branches.store',
                'clinic-branches.update',
                'clinic-branches.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('clinic.profile') => [
                'clinic.name' => ['required', 'string', 'max:120'],
                'clinic.code' => ['nullable', 'string', 'max:30'],
                'clinic.logo_path' => ['nullable', 'string', 'max:255'],
                'clinic.phone' => ['nullable', 'string', 'max:30'],
                'clinic.email' => ['nullable', 'email', 'max:120'],
                'clinic.address' => ['nullable', 'string', 'max:500'],
                'clinic.invoice_header' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('clinic-branches.store', 'clinic-branches.update') => [
                'branch.name' => ['required', 'string', 'max:120'],
                'branch.code' => ['nullable', 'string', 'max:30'],
                'branch.phone' => ['nullable', 'string', 'max:30'],
                'branch.address' => ['nullable', 'string', 'max:500'],
                'branch.opening_time' => ['nullable', 'date_format:H:i'],
                'branch.closing_time' => ['nullable', 'date_format:H:i'],
                'branch.queue_prefix' => ['nullable', 'string', 'max:10'],
                'branch.queue_number_padding' => ['required', 'integer', 'min:2', 'max:6'],
                'branch.is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('clinic-branches.store', 'clinic-branches.update')) {
            $branch = (array) $this->input('branch', []);
            $branch['is_active'] = filter_var($branch['is_active'] ?? false, FILTER_VALIDATE_BOOL);

            $this->merge([
                'branch' => $branch,
            ]);
        }
    }
}
