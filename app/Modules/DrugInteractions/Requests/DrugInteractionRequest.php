<?php

namespace App\Modules\DrugInteractions\Requests;

use App\Models\DrugInteractionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class DrugInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'drug-interactions.store',
                'drug-interactions.update',
                'drug-interactions.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('drug-interactions.store', 'drug-interactions.update') => [
                'code' => [
                    'required',
                    'string',
                    'max:40',
                    Rule::unique('drug_interaction_rules', 'code')->ignore($this->route('rule')),
                ],
                'left_operand_type' => ['required', Rule::in(['ingredient', 'class', 'generic'])],
                'left_operand_value' => ['required', 'string', 'max:160'],
                'right_operand_type' => ['required', Rule::in(['ingredient', 'class', 'generic'])],
                'right_operand_value' => ['required', 'string', 'max:160'],
                'severity' => ['required', Rule::in(['minor', 'moderate', 'major', 'contraindicated'])],
                'title' => ['required', 'string', 'max:180'],
                'clinical_effect' => ['nullable', 'string', 'max:2000'],
                'management_advice' => ['nullable', 'string', 'max:2000'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if (! $this->routeIs('drug-interactions.store', 'drug-interactions.update')) {
            return;
        }

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'title' => trim((string) $this->input('title')),
            'left_operand_value' => strtolower(trim((string) $this->input('left_operand_value'))),
            'right_operand_value' => strtolower(trim((string) $this->input('right_operand_value'))),
            'clinical_effect' => $this->input('clinical_effect') ?: null,
            'management_advice' => $this->input('management_advice') ?: null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        if (! $this->routeIs('drug-interactions.store', 'drug-interactions.update')) {
            return;
        }

        $validator->after(function (Validator $validator): void {
            $ruleId = $this->route('rule')?->id;
            $leftType = $this->input('left_operand_type');
            $leftValue = $this->input('left_operand_value');
            $rightType = $this->input('right_operand_type');
            $rightValue = $this->input('right_operand_value');

            $duplicateExists = DrugInteractionRule::query()
                ->when($ruleId, fn ($query) => $query->whereKeyNot($ruleId))
                ->where(function ($query) use ($leftType, $leftValue, $rightType, $rightValue): void {
                    $query
                        ->where(function ($direct) use ($leftType, $leftValue, $rightType, $rightValue): void {
                            $direct
                                ->where('left_operand_type', $leftType)
                                ->where('left_operand_value', $leftValue)
                                ->where('right_operand_type', $rightType)
                                ->where('right_operand_value', $rightValue);
                        })
                        ->orWhere(function ($reverse) use ($leftType, $leftValue, $rightType, $rightValue): void {
                            $reverse
                                ->where('left_operand_type', $rightType)
                                ->where('left_operand_value', $rightValue)
                                ->where('right_operand_type', $leftType)
                                ->where('right_operand_value', $leftValue);
                        });
                })
                ->exists();

            if ($duplicateExists) {
                $validator->errors()->add('code', 'Pasangan interaction rule ini sudah ada, baik dalam urutan langsung maupun terbalik.');
            }
        });
    }
}
