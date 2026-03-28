<?php

namespace App\Modules\PurchaseReturns\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'purchase-returns.store',
                'purchase-returns.update',
                'purchase-returns.complete',
                'purchase-returns.cancel',
                'purchase-returns.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('purchase-returns.store', 'purchase-returns.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')],
                'return_date' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.medicine_batch_id' => ['required', 'integer', 'distinct', Rule::exists('medicine_batches', 'id')],
                'items.*.quantity_returned' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'items.*.reason' => ['nullable', 'string', 'max:255'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            $this->routeIs('purchase-returns.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('purchase-returns.store', 'purchase-returns.update')) {
            $items = collect($this->input('items', []))
                ->map(fn ($item): array => [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'medicine_batch_id' => filled($item['medicine_batch_id'] ?? null) ? (int) $item['medicine_batch_id'] : null,
                    'quantity_returned' => filled($item['quantity_returned'] ?? null) ? (float) $item['quantity_returned'] : null,
                    'reason' => $item['reason'] ?: null,
                    'notes' => $item['notes'] ?: null,
                ])
                ->all();

            $this->merge([
                'branch_id' => (int) $this->input('branch_id'),
                'supplier_id' => (int) $this->input('supplier_id'),
                'notes' => $this->input('notes') ?: null,
                'items' => $items,
            ]);
        }

        if ($this->routeIs('purchase-returns.cancel')) {
            $this->merge([
                'notes' => $this->input('notes') ?: null,
            ]);
        }
    }
}
