<?php

namespace App\Modules\Laboratory\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LaboratoryOrderUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('laboratory-orders.store') => $this->canAccess('create laboratory management'),
            $this->routeIs('laboratory-orders.update') => $this->canAccess('edit laboratory management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'visit_registration_id' => ['required', 'integer', Rule::exists('visit_registrations', 'id')],
            'laboratory_test_id' => ['required', 'integer', Rule::exists('laboratory_tests', 'id')],
            'provider_type' => ['required', Rule::in(['internal', 'external'])],
            'partner_name' => ['nullable', 'string', 'max:160'],
            'external_reference_no' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['ordered', 'sample_collected', 'processing', 'sent_to_partner', 'resulted', 'reviewed', 'cancelled'])],
            'result_attachment_path' => ['nullable', 'string', 'max:255'],
            'result_summary' => ['nullable', 'string'],
            'result_impression' => ['nullable', 'string'],
            'result_lines' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'partner_name' => filled($this->input('partner_name')) ? trim((string) $this->input('partner_name')) : null,
            'external_reference_no' => filled($this->input('external_reference_no')) ? trim((string) $this->input('external_reference_no')) : null,
            'result_attachment_path' => filled($this->input('result_attachment_path')) ? trim((string) $this->input('result_attachment_path')) : null,
            'result_summary' => filled($this->input('result_summary')) ? trim((string) $this->input('result_summary')) : null,
            'result_impression' => filled($this->input('result_impression')) ? trim((string) $this->input('result_impression')) : null,
            'result_lines' => filled($this->input('result_lines')) ? trim((string) $this->input('result_lines')) : null,
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
