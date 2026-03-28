<?php

namespace App\Modules\Suppliers\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'suppliers.store',
                'suppliers.update',
                'suppliers.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('suppliers.store', 'suppliers.update') => [
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('suppliers', 'code')->ignore($this->route('supplier')),
                ],
                'name' => ['required', 'string', 'max:160'],
                'contact_person' => ['nullable', 'string', 'max:160'],
                'phone' => ['nullable', 'string', 'max:40'],
                'email' => ['nullable', 'email', 'max:160'],
                'npwp' => ['nullable', 'string', 'max:40'],
                'payment_term_days' => ['nullable', 'integer', 'min:0', 'max:365'],
                'address' => ['nullable', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_person' => $this->input('contact_person') ?: null,
            'phone' => $this->input('phone') ?: null,
            'email' => $this->input('email') ?: null,
            'npwp' => $this->input('npwp') ?: null,
            'payment_term_days' => $this->filled('payment_term_days') ? (int) $this->input('payment_term_days') : null,
            'address' => $this->input('address') ?: null,
            'notes' => $this->input('notes') ?: null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
