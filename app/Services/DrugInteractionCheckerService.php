<?php

namespace App\Services;

use App\Models\DrugInteractionRule;
use App\Models\Medicine;
use App\Models\Prescription;
use App\Models\PrescriptionInteractionOverride;
use App\Models\PrescriptionItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DrugInteractionCheckerService
{
    /**
     * @var array<string, \Illuminate\Support\Collection<int, PrescriptionItem>>
     */
    private array $activeMedicationCache = [];

    /**
     * @var \Illuminate\Support\Collection<int, DrugInteractionRule>|null
     */
    private ?Collection $ruleCache = null;

    public function alertsForItem(PrescriptionItem $item): array
    {
        $item->loadMissing([
            'medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
            'compoundIngredients.medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
            'prescription:id,patient_id,branch_id',
            'prescription.items:id,prescription_id,medicine_id,item_type,display_name,status,duration_days',
            'prescription.items.medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
            'prescription.items.compoundIngredients.medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
            'prescription.interactionOverrides:id,prescription_id,interaction_key',
            'prescription.visitRegistration:id,patient_id,visit_date',
        ]);

        $currentProfiles = $this->profilesForItem($item);

        if ($currentProfiles->isEmpty()) {
            return [];
        }

        $alerts = collect();

        foreach ($this->currentPrescriptionComparisons($item) as $comparison) {
            $alerts = $alerts->merge($this->matchProfiles(
                $item->prescription,
                $currentProfiles,
                $this->profilesForItem($comparison),
                'current_prescription',
                $comparison->display_name ?: 'Prescription item',
                $this->pairInteractionKey((int) $item->prescription_id, (int) $item->id, (int) $comparison->id),
            ));
        }

        foreach ($this->activeMedicationComparisons($item) as $comparison) {
            $alerts = $alerts->merge($this->matchProfiles(
                $item->prescription,
                $currentProfiles,
                $this->profilesForItem($comparison),
                'active_medication',
                $comparison->display_name ?: 'Active medication',
                $this->activeMedicationInteractionKey((int) $item->prescription_id, (int) $comparison->id),
            ));
        }

        return $alerts
            ->unique('interaction_key')
            ->values()
            ->all();
    }

    public function validatePrescription(Prescription $prescription): void
    {
        $prescription->loadMissing([
            'items.medicine',
            'items.compoundIngredients.medicine',
            'interactionOverrides',
            'visitRegistration',
        ]);

        $alerts = $prescription->items
            ->filter(fn (PrescriptionItem $item): bool => ! $item->isClosedCancelled())
            ->flatMap(fn (PrescriptionItem $item): array => $this->alertsForItem($item));

        $blocking = $alerts->first(fn (array $alert): bool => ($alert['blocking'] ?? false) === true && ($alert['overridden'] ?? false) === false);

        if ($blocking) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'prescription' => $blocking['message'],
            ]);
        }

        $pendingOverride = $alerts->first(fn (array $alert): bool => ($alert['requires_override'] ?? false) === true && ($alert['overridden'] ?? false) === false);

        if ($pendingOverride) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'prescription' => 'Ada interaksi obat major yang membutuhkan override dengan alasan sebelum prescription bisa difinalkan.',
            ]);
        }
    }

    public function validateDispense(PrescriptionItem $item): void
    {
        $alerts = collect($this->alertsForItem($item));

        $blocking = $alerts->first(fn (array $alert): bool => ($alert['blocking'] ?? false) === true && ($alert['overridden'] ?? false) === false);

        if ($blocking) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dispense' => $blocking['message'],
            ]);
        }

        if ($alerts->contains(fn (array $alert): bool => ($alert['requires_override'] ?? false) === true && ($alert['overridden'] ?? false) === false)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'dispense' => 'Item ini memiliki interaksi major yang belum dioverride. Isi alasan override terlebih dahulu.',
            ]);
        }
    }

    public function overrideMajorAlerts(PrescriptionItem $item, string $reason, User $actor): void
    {
        $item->loadMissing('prescription');

        $alerts = collect($this->alertsForItem($item))
            ->filter(fn (array $alert): bool => ($alert['requires_override'] ?? false) === true && ($alert['overridden'] ?? false) === false)
            ->values();

        if ($alerts->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'override_reason' => 'Tidak ada alert major yang perlu dioverride untuk item ini.',
            ]);
        }

        foreach ($alerts as $alert) {
            PrescriptionInteractionOverride::query()->updateOrCreate(
                [
                    'prescription_id' => $item->prescription_id,
                    'interaction_key' => $alert['interaction_key'],
                ],
                [
                    'prescription_item_id' => $item->id,
                    'drug_interaction_rule_id' => $alert['rule_id'],
                    'interaction_title' => $alert['title'],
                    'severity' => $alert['severity'],
                    'reason' => $reason,
                    'overridden_by_user_id' => $actor->getKey(),
                    'overridden_at' => now(),
                ]
            );
        }
    }

    private function matchProfiles(
        Prescription $prescription,
        Collection $currentProfiles,
        Collection $comparisonProfiles,
        string $source,
        string $comparisonLabel,
        string $interactionKeyPrefix,
    ): Collection {
        $overrides = $prescription->interactionOverrides
            ->keyBy('interaction_key');

        return $this->rules()->flatMap(function (DrugInteractionRule $rule) use (
            $currentProfiles,
            $comparisonProfiles,
            $source,
            $comparisonLabel,
            $interactionKeyPrefix,
            $overrides
        ) {
            $matched = $currentProfiles->contains(fn (array $currentProfile): bool => $this->operandMatches($currentProfile, $rule->left_operand_type, $rule->left_operand_value))
                && $comparisonProfiles->contains(fn (array $otherProfile): bool => $this->operandMatches($otherProfile, $rule->right_operand_type, $rule->right_operand_value));

            $reverseMatched = $currentProfiles->contains(fn (array $currentProfile): bool => $this->operandMatches($currentProfile, $rule->right_operand_type, $rule->right_operand_value))
                && $comparisonProfiles->contains(fn (array $otherProfile): bool => $this->operandMatches($otherProfile, $rule->left_operand_type, $rule->left_operand_value));

            if (! $matched && ! $reverseMatched) {
                return [];
            }

            $interactionKey = sprintf('%s:%s', $interactionKeyPrefix, $rule->id);
            $override = $overrides->get($interactionKey);

            return [[
                'type' => 'interaction',
                'level' => in_array($rule->severity, ['major', 'contraindicated'], true) ? 'danger' : 'warning',
                'severity' => $rule->severity,
                'rule_id' => $rule->id,
                'title' => $rule->title,
                'source' => $source,
                'interaction_key' => $interactionKey,
                'message' => sprintf(
                    '%s dengan %s. %s',
                    $rule->title,
                    $comparisonLabel,
                    trim((string) ($rule->clinical_effect ?: $rule->management_advice ?: 'Review kombinasi obat ini.'))
                ),
                'management_advice' => $rule->management_advice,
                'blocking' => $rule->severity === 'contraindicated',
                'requires_override' => $rule->severity === 'major',
                'overridden' => $override !== null,
                'override_reason' => $override?->reason,
            ]];
        });
    }

    private function currentPrescriptionComparisons(PrescriptionItem $item): Collection
    {
        return $item->prescription?->items
            ?->filter(function (PrescriptionItem $otherItem) use ($item): bool {
                return (int) $otherItem->id !== (int) $item->id
                    && ! $otherItem->isClosedCancelled();
            })
            ->values() ?? collect();
    }

    private function activeMedicationComparisons(PrescriptionItem $item): Collection
    {
        $patientId = (int) ($item->prescription?->patient_id ?? 0);
        $visitDate = $item->prescription?->visitRegistration?->visit_date;

        if (! $patientId || ! $visitDate) {
            return collect();
        }

        $cacheKey = sprintf('%s:%s:%s', $patientId, $item->prescription_id, $visitDate->toDateString());

        if (! array_key_exists($cacheKey, $this->activeMedicationCache)) {
            $candidateItems = PrescriptionItem::query()
                ->with([
                    'medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
                    'compoundIngredients.medicine:id,code,name,generic_name,active_ingredients,therapeutic_class',
                    'prescription:id,patient_id,visit_registration_id',
                    'prescription.visitRegistration:id,visit_date',
                ])
                ->whereHas('prescription', function (Builder $query) use ($patientId, $item): void {
                    $query
                        ->where('patient_id', $patientId)
                        ->where('id', '!=', $item->prescription_id);
                })
                ->whereNotIn('status', ['cancelled', 'partial_cancelled'])
                ->get()
                ->filter(function (PrescriptionItem $historyItem) use ($visitDate): bool {
                    $historyVisitDate = $historyItem->prescription?->visitRegistration?->visit_date;

                    if (! $historyVisitDate) {
                        return false;
                    }

                    $durationDays = max(1, (int) ($historyItem->duration_days ?? 0));
                    $therapyEndDate = $historyVisitDate->copy()->addDays($durationDays);

                    return $therapyEndDate->gte($visitDate) || $historyVisitDate->gte($visitDate->copy()->subDays(30));
                })
                ->values();

            $this->activeMedicationCache[$cacheKey] = $candidateItems;
        }

        return $this->activeMedicationCache[$cacheKey];
    }

    private function profilesForItem(PrescriptionItem $item): Collection
    {
        return collect([$item->medicine])
            ->merge($item->compoundIngredients->map(fn ($ingredient) => $ingredient->medicine))
            ->filter(fn ($medicine) => $medicine instanceof Medicine)
            ->map(fn (Medicine $medicine): array => [
                'medicine_id' => $medicine->id,
                'label' => trim($medicine->code . ' - ' . $medicine->name),
                'generic' => $this->normalizedKeywords($medicine->generic_name),
                'ingredient' => $this->normalizedKeywords($medicine->active_ingredients),
                'class' => $this->normalizedKeywords($medicine->therapeutic_class),
            ])
            ->values();
    }

    private function operandMatches(array $profile, string $operandType, string $operandValue): bool
    {
        $normalizedValue = mb_strtolower(trim($operandValue));

        if ($normalizedValue === '') {
            return false;
        }

        return collect($profile[$operandType] ?? [])
            ->contains(fn (string $value): bool => $value === $normalizedValue);
    }

    private function pairInteractionKey(int $prescriptionId, int $firstItemId, int $secondItemId): string
    {
        $pair = collect([$firstItemId, $secondItemId])->sort()->implode('-');

        return sprintf('rxpair:%s:%s', $prescriptionId, $pair);
    }

    private function activeMedicationInteractionKey(int $prescriptionId, int $historyItemId): string
    {
        return sprintf('active:%s:%s', $prescriptionId, $historyItemId);
    }

    private function normalizedKeywords(?string $value): array
    {
        if (! filled($value)) {
            return [];
        }

        return collect(preg_split('/[\n,;\/]+/', mb_strtolower((string) $value)))
            ->map(fn ($entry) => trim((string) $entry))
            ->filter(fn ($entry) => $entry !== '')
            ->values()
            ->all();
    }

    private function rules(): Collection
    {
        if ($this->ruleCache instanceof Collection) {
            return $this->ruleCache;
        }

        return $this->ruleCache = DrugInteractionRule::query()
            ->where('is_active', true)
            ->orderBy('severity')
            ->orderBy('code')
            ->get();
    }
}
