<?php

namespace App\Modules\Billing\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('billing.refresh', 'billing.pay', 'billing.print', 'billing.receipt') => $this->user() !== null,
            $this->routeIs('billing.tempo') => $this->user()?->hasAnyRole(['super-admin', 'clinic-admin', 'cashier', 'front-office']) ?? false,
            $this->routeIs('billing.void') => $this->user()?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('billing.pay') => [
                'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
                'amount' => ['nullable', 'numeric', 'min:0.01'],
                'payment_reference' => ['nullable', 'string', 'max:120'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('billing.tempo') => [
                'due_date' => ['required', 'date'],
                'payer_type' => ['required', 'in:self_pay,corporate'],
                'payer_name' => ['nullable', 'string', 'max:190'],
                'payer_contact_person' => ['nullable', 'string', 'max:160'],
                'payer_phone' => ['nullable', 'string', 'max:40'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('billing.void') => [
                'void_reason' => ['required', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => $this->filled('amount') ? (float) $this->input('amount') : null,
            'payment_reference' => $this->input('payment_reference') ?: null,
            'due_date' => $this->input('due_date') ?: null,
            'payer_type' => $this->input('payer_type') ?: 'self_pay',
            'payer_name' => $this->input('payer_name') ?: null,
            'payer_contact_person' => $this->input('payer_contact_person') ?: null,
            'payer_phone' => $this->input('payer_phone') ?: null,
            'void_reason' => $this->input('void_reason') ?: null,
            'notes' => $this->input('notes') ?: null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->routeIs('billing.tempo') && $this->input('payer_type') === 'corporate' && ! $this->filled('payer_name')) {
                $validator->errors()->add('payer_name', 'Nama perusahaan wajib diisi untuk piutang perusahaan.');
            }
        });
    }
}
