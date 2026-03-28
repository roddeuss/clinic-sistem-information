<?php

namespace App\Modules\CashierShifts\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CashierShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'cashier-shifts.store',
                'cashier-shifts.close',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('cashier-shifts.store') => [
                'counter_id' => ['required', 'integer', Rule::exists('counters', 'id')],
                'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'opening_notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('cashier-shifts.close') => [
                'closing_balance' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'closing_notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'opening_notes' => $this->input('opening_notes') ?: null,
            'closing_notes' => $this->input('closing_notes') ?: null,
        ]);
    }
}
