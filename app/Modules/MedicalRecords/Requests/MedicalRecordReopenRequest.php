<?php

namespace App\Modules\MedicalRecords\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalRecordReopenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->routeIs('medical-records.approve-reopen')) {
            return $user->hasAnyRole(['super-admin', 'clinic-admin']);
        }

        return $user->hasRole('super-admin')
            || $user->hasAnyRole(['doctor', 'clinic-admin'])
            || $user->can('edit medical record management');
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason', '')),
        ]);
    }

    public function payload(): array
    {
        return [
            'reason' => (string) $this->validated('reason'),
        ];
    }
}
