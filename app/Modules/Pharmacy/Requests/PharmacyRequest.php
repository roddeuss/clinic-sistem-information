<?php

namespace App\Modules\Pharmacy\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PharmacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'pharmacy.store',
                'pharmacy.update',
                'pharmacy.delete',
                'medicine-batches.store',
                'medicine-batches.update',
                'medicine-batches.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('pharmacy.store', 'pharmacy.update') => [
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('medicines', 'code')->ignore($this->route('medicine')),
                ],
                'name' => ['required', 'string', 'max:160'],
                'product_category_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
                'generic_name' => ['nullable', 'string', 'max:160'],
                'active_ingredients' => ['nullable', 'string', 'max:2000'],
                'allergy_keywords' => ['nullable', 'string', 'max:2000'],
                'dosage_form' => ['required', 'string', 'max:60'],
                'therapeutic_class' => ['nullable', 'string', 'max:120'],
                'strength' => ['nullable', 'string', 'max:80'],
                'base_unit' => ['nullable', 'string', 'max:40'],
                'uoms' => ['required', 'array', 'min:1'],
                'uoms.*.id' => ['nullable', 'integer'],
                'uoms.*.label' => ['required', 'string', 'max:40'],
                'uoms.*.conversion_factor' => ['required', 'numeric', 'gt:0', 'max:999999999.9999'],
                'uoms.*.allow_purchase' => ['required', 'boolean'],
                'uoms.*.allow_dispense' => ['required', 'boolean'],
                'uoms.*.is_base' => ['required', 'boolean'],
                'uoms.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999999'],
                'description' => ['nullable', 'string', 'max:1000'],
                'contraindication_notes' => ['nullable', 'string', 'max:2000'],
                'branch_prices' => ['nullable', 'array'],
                'branch_prices.*' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
                'is_compoundable' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('medicine-batches.store', 'medicine-batches.update') => [
                'medicine_id' => ['required', 'integer', Rule::exists('medicines', 'id')],
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'batch_number' => [
                    'required',
                    'string',
                    'max:80',
                    Rule::unique('medicine_batches', 'batch_number')
                        ->where(fn ($query) => $query
                            ->where('medicine_id', $this->input('medicine_id'))
                            ->where('branch_id', $this->input('branch_id')))
                        ->ignore($this->route('batch')),
                ],
                'received_at' => ['required', 'date'],
                'expired_at' => ['nullable', 'date', 'after_or_equal:received_at'],
                'quantity_received' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
                'quantity_available' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'purchase_cost' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
                'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
                'supplier_name' => ['nullable', 'string', 'max:160'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('pharmacy.store', 'pharmacy.update')) {
            $prices = collect($this->input('branch_prices', []))
                ->map(fn ($value) => filled($value) ? (float) $value : null)
                ->all();

            $uoms = collect($this->input('uoms', []))
                ->map(function ($row, int $index) {
                    return [
                        'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
                        'label' => strtoupper(trim((string) ($row['label'] ?? ''))),
                        'conversion_factor' => filled($row['conversion_factor'] ?? null) ? (float) $row['conversion_factor'] : null,
                        'allow_purchase' => filter_var($row['allow_purchase'] ?? false, FILTER_VALIDATE_BOOL),
                        'allow_dispense' => filter_var($row['allow_dispense'] ?? false, FILTER_VALIDATE_BOOL),
                        'is_base' => filter_var($row['is_base'] ?? false, FILTER_VALIDATE_BOOL),
                        'sort_order' => filled($row['sort_order'] ?? null) ? (int) $row['sort_order'] : (($index + 1) * 10),
                    ];
                })
                ->filter(fn (array $row): bool => $row['label'] !== '')
                ->values()
                ->all();

            if ($uoms === []) {
                $label = strtoupper(trim((string) ($this->input('base_unit') ?: 'UNIT')));
                $uoms = [[
                    'id' => null,
                    'label' => $label,
                    'conversion_factor' => 1,
                    'allow_purchase' => true,
                    'allow_dispense' => true,
                    'is_base' => true,
                    'sort_order' => 10,
                ]];
            }

            $this->merge([
                'product_category_id' => filled($this->input('product_category_id')) ? (int) $this->input('product_category_id') : null,
                'generic_name' => $this->input('generic_name') ?: null,
                'active_ingredients' => $this->input('active_ingredients') ?: null,
                'allergy_keywords' => $this->input('allergy_keywords') ?: null,
                'therapeutic_class' => $this->input('therapeutic_class') ?: null,
                'strength' => $this->input('strength') ?: null,
                'base_unit' => $this->input('base_unit') ?: null,
                'uoms' => $uoms,
                'description' => $this->input('description') ?: null,
                'contraindication_notes' => $this->input('contraindication_notes') ?: null,
                'branch_prices' => $prices,
                'is_compoundable' => $this->boolean('is_compoundable'),
                'is_active' => $this->boolean('is_active'),
            ]);
        }

        if ($this->routeIs('medicine-batches.store', 'medicine-batches.update')) {
            $this->merge([
                'expired_at' => $this->input('expired_at') ?: null,
                'purchase_cost' => filled($this->input('purchase_cost')) ? (float) $this->input('purchase_cost') : null,
                'supplier_id' => filled($this->input('supplier_id')) ? (int) $this->input('supplier_id') : null,
                'supplier_name' => $this->input('supplier_name') ?: null,
                'notes' => $this->input('notes') ?: null,
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
