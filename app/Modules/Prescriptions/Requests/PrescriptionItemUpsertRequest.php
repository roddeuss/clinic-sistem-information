<?php

namespace App\Modules\Prescriptions\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrescriptionItemUpsertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('prescription-items.store') => $this->canAccess('create prescription management'),
            $this->routeIs('prescription-items.update') => $this->canAccess('edit prescription management'),
            default => false,
        };
    }

    public function rules(): array
    {
        return [
            'prescription_id' => ['required', 'integer', Rule::exists('prescriptions', 'id')],
            'item_type' => ['required', Rule::in(['in_house', 'external', 'compound'])],
            'medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')],
            'display_name' => ['nullable', 'string', 'max:160'],
            'route' => ['nullable', 'string', 'max:60'],
            'dose_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'dose_unit' => ['nullable', 'string', 'max:30'],
            'frequency' => ['nullable', 'string', 'max:120'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'instruction' => ['nullable', 'string', 'max:1000'],
            'quantity_prescribed' => ['required', 'numeric', 'gt:0', 'max:999999999.99'],
            'dispense_unit' => ['nullable', 'string', 'max:30'],
            'weight_snapshot_kg' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'status' => ['nullable', Rule::in(['pending', 'cancelled', 'external'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'compound_ingredients' => ['nullable', 'array'],
            'compound_ingredients.*.medicine_id' => ['nullable', 'integer', Rule::exists('medicines', 'id')],
            'compound_ingredients.*.quantity_required' => ['nullable', 'numeric', 'gt:0', 'max:999999999.99'],
            'compound_ingredients.*.unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $ingredients = collect($this->input('compound_ingredients', []))
            ->map(function ($row): array {
                return [
                    'medicine_id' => filled($row['medicine_id'] ?? null) ? (int) $row['medicine_id'] : null,
                    'quantity_required' => filled($row['quantity_required'] ?? null) ? (float) $row['quantity_required'] : null,
                    'unit' => filled($row['unit'] ?? null) ? trim((string) $row['unit']) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['medicine_id'] !== null && $row['quantity_required'] !== null)
            ->values()
            ->all();

        $this->merge([
            'medicine_id' => filled($this->input('medicine_id')) ? (int) $this->input('medicine_id') : null,
            'display_name' => filled($this->input('display_name')) ? trim((string) $this->input('display_name')) : null,
            'route' => filled($this->input('route')) ? trim((string) $this->input('route')) : null,
            'dose_amount' => filled($this->input('dose_amount')) ? (float) $this->input('dose_amount') : null,
            'dose_unit' => filled($this->input('dose_unit')) ? trim((string) $this->input('dose_unit')) : null,
            'frequency' => filled($this->input('frequency')) ? trim((string) $this->input('frequency')) : null,
            'duration_days' => filled($this->input('duration_days')) ? (int) $this->input('duration_days') : null,
            'instruction' => filled($this->input('instruction')) ? trim((string) $this->input('instruction')) : null,
            'dispense_unit' => filled($this->input('dispense_unit')) ? trim((string) $this->input('dispense_unit')) : null,
            'weight_snapshot_kg' => filled($this->input('weight_snapshot_kg')) ? (float) $this->input('weight_snapshot_kg') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
            'status' => filled($this->input('status')) ? (string) $this->input('status') : null,
            'compound_ingredients' => $ingredients,
        ]);
    }

    public function payload(): array
    {
        return $this->validated();
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
