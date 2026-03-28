<?php

namespace App\Modules\ExpiryMonitoring\Services;

use App\Models\Branch;
use App\Models\MedicineBatch;
use App\Services\AuditLogService;
use App\Services\ReorderPointService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ExpiryMonitoringService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly ReorderPointService $reorderPointService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? 'attention'),
            'window_days' => max(1, min(180, (int) ($filters['window_days'] ?? 30))),
        ];

        return [
            'filters' => $filters,
            'batches' => $this->table($filters),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'code', 'name']),
            'abilities' => $this->abilities(),
        ];
    }

    public function updateBatchStatus(MedicineBatch $batch, array $payload): MedicineBatch
    {
        $action = $payload['action'];
        $before = $batch->fresh($this->relations())?->toArray() ?? [];

        if ($action === 'release' && $batch->isExpired()) {
            throw ValidationException::withMessages([
                'expiry_monitoring' => 'Batch yang sudah expired tidak boleh di-release ke stok aktif.',
            ]);
        }

        $attributes = $action === 'quarantine'
            ? [
                'is_active' => false,
                'quarantined_at' => now(),
                'quarantined_by_user_id' => auth()->id(),
                'quarantine_reason' => $payload['quarantine_reason'],
            ]
            : [
                'is_active' => true,
                'quarantined_at' => null,
                'quarantined_by_user_id' => null,
                'quarantine_reason' => null,
            ];

        $batch->update($attributes);

        $fresh = $batch->fresh($this->relations());

        $this->auditLogService->log(
            'expiry_monitoring',
            $action === 'quarantine' ? 'quarantined' : 'released',
            $fresh,
            $action === 'quarantine'
                ? 'Batch dipindahkan ke status quarantine dari expiry monitoring.'
                : 'Batch di-release kembali ke stok aktif dari expiry monitoring.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'action' => $action,
                'quarantine_reason' => $payload['quarantine_reason'] ?? null,
            ],
        );

        $this->reorderPointService->refreshForMedicineBranch((int) $fresh->medicine_id, (int) $fresh->branch_id);

        return $fresh;
    }

    private function table(array $filters): LengthAwarePaginator
    {
        $windowEnd = now()->addDays((int) $filters['window_days'])->toDateString();

        return MedicineBatch::query()
            ->with($this->relations())
            ->whereNotNull('expired_at')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('batch_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('medicine', function (Builder $medicineQuery) use ($filters): void {
                            $medicineQuery
                                ->where('code', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('name', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', function (Builder $query) use ($filters, $windowEnd): void {
                match ($filters['status']) {
                    'expired' => $query->whereDate('expired_at', '<', now()->toDateString()),
                    'near_expiry' => $query->whereDate('expired_at', '>=', now()->toDateString())->whereDate('expired_at', '<=', $windowEnd),
                    'quarantined' => $query->whereNotNull('quarantined_at'),
                    'healthy' => $query->whereDate('expired_at', '>', $windowEnd)->whereNull('quarantined_at'),
                    default => $query->where(function (Builder $attentionQuery) use ($windowEnd): void {
                        $attentionQuery
                            ->whereDate('expired_at', '<', now()->toDateString())
                            ->orWhere(function (Builder $nearExpiryQuery) use ($windowEnd): void {
                                $nearExpiryQuery
                                    ->whereDate('expired_at', '>=', now()->toDateString())
                                    ->whereDate('expired_at', '<=', $windowEnd);
                            })
                            ->orWhereNotNull('quarantined_at');
                    }),
                };
            })
            ->orderBy('expired_at')
            ->orderBy('branch_id')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'branch:id,code,name',
            'medicine:id,code,name,strength,base_unit',
            'supplier:id,code,name',
        ];
    }

    private function abilities(): array
    {
        return [
            'edit' => auth()->check(),
        ];
    }
}
