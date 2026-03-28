<?php

namespace App\Modules\MedicalServices\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MedicalServiceOrderUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('medical-service-orders.store') => $this->canAccess('create medical service management'),
            $this->routeIs('medical-service-orders.update') => $this->canAccess('edit medical service management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'medical_service_id' => ['required', 'integer', Rule::exists('medical_services', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'status' => ['required', Rule::in(['ordered', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity' => (float) $this->input('quantity'),
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
