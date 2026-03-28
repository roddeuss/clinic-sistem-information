<?php

namespace App\Modules\StockAdjustments\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'stock-adjustments.store',
                'stock-adjustments.update',
                'stock-adjustments.apply',
                'stock-adjustments.cancel',
                'stock-adjustments.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('stock-adjustments.store', 'stock-adjustments.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'adjustment_type' => ['required', Rule::in(['increase', 'decrease'])],
                'adjustment_date' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.medicine_batch_id' => ['required', 'integer', 'distinct', Rule::exists('medicine_batches', 'id')],
                'items.*.quantity_adjusted' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'items.*.reason' => ['nullable', 'string', 'max:255'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            $this->routeIs('stock-adjustments.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('stock-adjustments.store', 'stock-adjustments.update')) {
            $items = collect($this->input('items', []))
                ->map(fn ($item): array => [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'medicine_batch_id' => filled($item['medicine_batch_id'] ?? null) ? (int) $item['medicine_batch_id'] : null,
                    'quantity_adjusted' => filled($item['quantity_adjusted'] ?? null) ? (float) $item['quantity_adjusted'] : null,
                    'reason' => $item['reason'] ?: null,
                    'notes' => $item['notes'] ?: null,
                ])
                ->all();

            $this->merge([
                'branch_id' => (int) $this->input('branch_id'),
                'notes' => $this->input('notes') ?: null,
                'items' => $items,
            ]);
        }

        if ($this->routeIs('stock-adjustments.cancel')) {
            $this->merge([
                'notes' => $this->input('notes') ?: null,
            ]);
        }
    }
}
