<?php

namespace App\Modules\ReorderPoints\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;
use App\Models\MedicineReorderPolicy;

class ReorderPointRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'reorder-points.store',
                'reorder-points.update',
                'reorder-points.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('reorder-points.store', 'reorder-points.update') => [
                'medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')],
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'preferred_purchase_unit_id' => ['nullable', 'integer', Rule::exists('medicine_units', 'id')],
                'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'safety_stock' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'reorder_point' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'reorder_quantity' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
                'supplier_preferences' => ['nullable', 'array'],
                'supplier_preferences.*.supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
                'supplier_preferences.*.priority' => ['nullable', 'integer', 'min:1', 'max:9999'],
                'supplier_preferences.*.is_primary' => ['nullable', 'boolean'],
                'supplier_preferences.*.is_active' => ['nullable', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('reorder-points.store', 'reorder-points.update')) {
            return;
        }

        $supplierPreferences = collect($this->input('supplier_preferences', []))
            ->map(function ($row): array {
                return [
                    'supplier_id' => filled($row['supplier_id'] ?? null) ? (int) $row['supplier_id'] : null,
                    'priority' => filled($row['priority'] ?? null) ? (int) $row['priority'] : null,
                    'is_primary' => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOL),
                    'is_active' => array_key_exists('is_active', (array) $row)
                        ? filter_var($row['is_active'], FILTER_VALIDATE_BOOL)
                        : true,
                ];
            })
            ->filter(fn (array $row): bool => $row['supplier_id'] !== null)
            ->values()
            ->all();

        $this->merge([
            'preferred_purchase_unit_id' => filled($this->input('preferred_purchase_unit_id'))
                ? (int) $this->input('preferred_purchase_unit_id')
                : null,
            'minimum_stock' => (float) $this->input('minimum_stock', 0),
            'safety_stock' => (float) $this->input('safety_stock', 0),
            'reorder_point' => (float) $this->input('reorder_point', 0),
            'reorder_quantity' => (float) $this->input('reorder_quantity', 0),
            'lead_time_days' => (int) $this->input('lead_time_days', 0),
            'notes' => $this->input('notes') ?: null,
            'is_active' => $this->boolean('is_active', true),
            'supplier_preferences' => $supplierPreferences,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->routeIs('reorder-points.store', 'reorder-points.update')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $policyId = $this->route('policy')?->id;

            $exists = MedicineReorderPolicy::query()
                ->where('medicine_id', $this->input('medicine_id'))
                ->where('branch_id', $this->input('branch_id'))
                ->when($policyId, fn ($query) => $query->whereKeyNot($policyId))
                ->exists();

            if ($exists) {
                $validator->errors()->add('medicine_id', 'Policy reorder untuk medicine dan branch ini sudah ada.');
            }

            $supplierIds = collect($this->input('supplier_preferences', []))
                ->pluck('supplier_id')
                ->filter()
                ->values();

            if ($supplierIds->count() !== $supplierIds->unique()->count()) {
                $validator->errors()->add('supplier_preferences', 'Supplier priority tidak boleh duplikat dalam satu policy.');
            }
        });
    }
}
