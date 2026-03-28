<?php

namespace App\Modules\Procedures\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProcedureOrderUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('procedure-orders.store') => $this->canAccess('create procedure management'),
            $this->routeIs('procedure-orders.update') => $this->canAccess('edit procedure management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'procedure_master_id' => ['required', 'integer', Rule::exists('procedure_masters', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'status' => ['required', Rule::in(['ordered', 'in_progress', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
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
