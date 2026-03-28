<?php

namespace App\Modules\DrugInteractions\Services;

use App\Models\DrugInteractionRule;
use App\Models\PrescriptionInteractionOverride;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class DrugInteractionManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
            'severity' => (string) ($filters['severity'] ?? ''),
            'operand_type' => (string) ($filters['operand_type'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'rules' => $this->table($filters),
            'operandTypes' => ['ingredient', 'class', 'generic'],
            'severityOptions' => ['minor', 'moderate', 'major', 'contraindicated'],
            'metrics' => [
                'active_rules' => DrugInteractionRule::query()->where('is_active', true)->count(),
                'major_rules' => DrugInteractionRule::query()
                    ->where('is_active', true)
                    ->whereIn('severity', ['major', 'contraindicated'])
                    ->count(),
                'override_events' => PrescriptionInteractionOverride::query()->count(),
            ],
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): DrugInteractionRule
    {
        $rule = DrugInteractionRule::query()->create($this->attributes($payload));

        $this->auditLogService->log(
            'drug_interactions',
            'created',
            $rule,
            'Drug interaction rule baru dibuat.',
            [],
            $rule->fresh()->toArray(),
        );

        return $rule->fresh();
    }

    public function update(DrugInteractionRule $rule, array $payload): DrugInteractionRule
    {
        $before = $rule->toArray();
        $rule->update($this->attributes($payload));

        $fresh = $rule->fresh();

        $this->auditLogService->log(
            'drug_interactions',
            'updated',
            $fresh,
            'Drug interaction rule diperbarui.',
            $before,
            $fresh?->toArray() ?? [],
        );

        return $fresh;
    }

    public function delete(DrugInteractionRule $rule): void
    {
        $before = $rule->toArray();

        $rule->update([
            'is_active' => false,
        ]);

        $fresh = $rule->fresh();

        $this->auditLogService->log(
            'drug_interactions',
            'archived',
            $fresh,
            'Drug interaction rule diarsipkan.',
            $before,
            $fresh?->toArray() ?? [],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return DrugInteractionRule::query()
            ->withCount('overrides')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('title', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('left_operand_value', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('right_operand_value', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('clinical_effect', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('management_advice', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['severity'] !== '', fn (Builder $query) => $query->where('severity', $filters['severity']))
            ->when($filters['operand_type'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $operandQuery) use ($filters): void {
                    $operandQuery
                        ->where('left_operand_type', $filters['operand_type'])
                        ->orWhere('right_operand_type', $filters['operand_type']);
                });
            })
            ->orderByRaw("
                CASE severity
                    WHEN 'contraindicated' THEN 1
                    WHEN 'major' THEN 2
                    WHEN 'moderate' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('code')
            ->paginate(10)
            ->withQueryString();
    }

    private function attributes(array $payload): array
    {
        return [
            'code' => $payload['code'],
            'left_operand_type' => $payload['left_operand_type'],
            'left_operand_value' => $payload['left_operand_value'],
            'right_operand_type' => $payload['right_operand_type'],
            'right_operand_value' => $payload['right_operand_value'],
            'severity' => $payload['severity'],
            'title' => trim((string) $payload['title']),
            'clinical_effect' => $payload['clinical_effect'] ?? null,
            'management_advice' => $payload['management_advice'] ?? null,
            'is_active' => $payload['is_active'],
        ];
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }
}
