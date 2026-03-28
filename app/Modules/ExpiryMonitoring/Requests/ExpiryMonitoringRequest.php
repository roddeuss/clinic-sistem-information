<?php

namespace App\Modules\ExpiryMonitoring\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpiryMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('expiry-monitoring.update')
            ? $this->user() !== null
            : false;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['quarantine', 'release'])],
            'quarantine_reason' => ['nullable', 'string', 'max:255', Rule::requiredIf($this->input('action') === 'quarantine')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quarantine_reason' => $this->input('quarantine_reason') ?: null,
        ]);
    }
}
