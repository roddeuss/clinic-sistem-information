<?php

namespace App\Modules\StockOpnames\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOpnameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'stock-opnames.store',
                'stock-opnames.update',
                'stock-opnames.finalize',
                'stock-opnames.cancel',
                'stock-opnames.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('stock-opnames.store', 'stock-opnames.update') => [
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'opname_date' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.medicine_batch_id' => ['required', 'integer', 'distinct', Rule::exists('medicine_batches', 'id')],
                'items.*.counted_quantity' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            $this->routeIs('stock-opnames.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('stock-opnames.store', 'stock-opnames.update')) {
            $items = collect($this->input('items', []))
                ->map(fn ($item): array => [
                    'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                    'medicine_batch_id' => filled($item['medicine_batch_id'] ?? null) ? (int) $item['medicine_batch_id'] : null,
                    'counted_quantity' => filled($item['counted_quantity'] ?? null) ? (float) $item['counted_quantity'] : null,
                    'notes' => $item['notes'] ?: null,
                ])
                ->all();

            $this->merge([
                'branch_id' => (int) $this->input('branch_id'),
                'notes' => $this->input('notes') ?: null,
                'items' => $items,
            ]);
        }

        if ($this->routeIs('stock-opnames.cancel')) {
            $this->merge([
                'notes' => $this->input('notes') ?: null,
            ]);
        }
    }
}
