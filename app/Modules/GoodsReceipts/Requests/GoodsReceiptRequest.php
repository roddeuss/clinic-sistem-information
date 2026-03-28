<?php

namespace App\Modules\GoodsReceipts\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'goods-receipts.store',
                'goods-receipts.update',
                'goods-receipts.cancel',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('goods-receipts.store', 'goods-receipts.update') => [
                'purchase_order_id' => ['required', 'integer', Rule::exists('purchase_orders', 'id')],
                'received_at' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'items' => ['required', 'array', 'min:1'],
                'items.*.id' => ['nullable', 'integer'],
                'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_items', 'id')],
                'items.*.batch_number' => ['required', 'string', 'max:80'],
                'items.*.expired_at' => ['nullable', 'date', 'after_or_equal:received_at'],
                'items.*.quantity_received' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
                'items.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
                'items.*.notes' => ['nullable', 'string', 'max:500'],
            ],
            $this->routeIs('goods-receipts.cancel') => [
                'cancel_reason' => ['required', 'string', 'max:255'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('goods-receipts.store', 'goods-receipts.update')) {
            $items = collect($this->input('items', []))
                ->map(function ($item) {
                    return [
                        'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                        'purchase_order_item_id' => filled($item['purchase_order_item_id'] ?? null) ? (int) $item['purchase_order_item_id'] : null,
                        'batch_number' => trim((string) ($item['batch_number'] ?? '')),
                        'expired_at' => $item['expired_at'] ?: null,
                        'quantity_received' => filled($item['quantity_received'] ?? null) ? (float) $item['quantity_received'] : 0,
                        'unit_cost' => filled($item['unit_cost'] ?? null) ? (float) $item['unit_cost'] : 0,
                        'notes' => $item['notes'] ?: null,
                    ];
                })
                ->all();

            $this->merge([
                'purchase_order_id' => (int) $this->input('purchase_order_id'),
                'notes' => $this->input('notes') ?: null,
                'items' => $items,
            ]);
        }

        if ($this->routeIs('goods-receipts.cancel')) {
            $this->merge([
                'notes' => $this->input('notes') ?: null,
            ]);
        }
    }
}
