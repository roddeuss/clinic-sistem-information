<?php

namespace App\Modules\PurchaseOrders\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'purchase-orders.store',
                'purchase-orders.update',
                'purchase-orders.submit',
                'purchase-orders.approve',
                'purchase-orders.reject',
                'purchase-orders.cancel',
                'purchase-orders.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('purchase-orders.store', 'purchase-orders.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
                'order_date' => ['required', 'date'],
                'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.medicine_id' => ['required', 'integer', 'distinct', Rule::exists('medicines', 'id')],
                'items.*.medicine_unit_id' => ['nullable', 'integer', Rule::exists('medicine_units', 'id')],
                'items.*.quantity_ordered' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'items.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            $this->routeIs('purchase-orders.approve') => [
                'approval_notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('purchase-orders.reject') => [
                'rejection_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('purchase-orders.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('purchase-orders.store', 'purchase-orders.update')) {
            $items = collect($this->input('items', []))
                ->map(function ($item) {
                    return [
                        'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                        'medicine_id' => filled($item['medicine_id'] ?? null) ? (int) $item['medicine_id'] : null,
                        'medicine_unit_id' => filled($item['medicine_unit_id'] ?? null) ? (int) $item['medicine_unit_id'] : null,
                        'quantity_ordered' => filled($item['quantity_ordered'] ?? null) ? (float) $item['quantity_ordered'] : null,
                        'unit_cost' => filled($item['unit_cost'] ?? null) ? (float) $item['unit_cost'] : null,
                        'notes' => $item['notes'] ?: null,
                    ];
                })
                ->all();

            $this->merge([
                'branch_id' => (int) $this->input('branch_id'),
                'supplier_id' => (int) $this->input('supplier_id'),
                'expected_date' => $this->input('expected_date') ?: null,
                'notes' => $this->input('notes') ?: null,
                'items' => $items,
            ]);
        }

        if ($this->routeIs('purchase-orders.cancel')) {
            $this->merge([
                'notes' => $this->input('notes') ?: null,
            ]);
        }

        if ($this->routeIs('purchase-orders.approve')) {
            $this->merge([
                'approval_notes' => $this->input('approval_notes') ?: null,
            ]);
        }

        if ($this->routeIs('purchase-orders.reject')) {
            $this->merge([
                'rejection_reason' => $this->input('rejection_reason') ?: null,
                'notes' => $this->input('notes') ?: null,
            ]);
        }
    }
}
