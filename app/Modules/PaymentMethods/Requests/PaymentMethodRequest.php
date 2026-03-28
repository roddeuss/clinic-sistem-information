<?php

namespace App\Modules\PaymentMethods\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'payment-methods.store',
                'payment-methods.update',
                'payment-methods.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('payment-methods.store', 'payment-methods.update') => [
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('payment_methods', 'code')->ignore($this->route('paymentMethod')),
                ],
                'name' => ['required', 'string', 'max:160'],
                'type' => ['required', Rule::in(['cash', 'bank_transfer', 'debit_credit', 'qris'])],
                'description' => ['nullable', 'string', 'max:1000'],
                'is_cash' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->input('description') ?: null,
            'is_cash' => $this->boolean('is_cash'),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => (int) ($this->input('sort_order') ?: 0),
        ]);
    }
}
