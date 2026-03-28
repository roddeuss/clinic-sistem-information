<?php

namespace App\Modules\Receivables\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceivableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('receivables') => $this->user()?->hasAnyRole(['super-admin', 'clinic-admin', 'cashier', 'front-office']) ?? false,
            $this->routeIs('receivables.extend', 'receivables.settle') => $this->user()?->hasAnyRole(['super-admin', 'clinic-admin', 'cashier', 'front-office']) ?? false,
            $this->routeIs('receivables.cancel') => $this->user()?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('receivables.extend') => [
                'due_date' => ['required', 'date'],
                'extension_reason' => ['required', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('receivables.settle') => [
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'amount' => ['nullable', 'numeric', 'min:0.01'],
                'payment_reference' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('receivables.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->filled('amount') ? (float) $this->input('amount') : null,
            'extension_reason' => $this->input('extension_reason') ?: null,
            'payment_reference' => $this->input('payment_reference') ?: null,
            'cancel_reason' => $this->input('cancel_reason') ?: null,
            'notes' => $this->input('notes') ?: null,
        ]);
    }
}
