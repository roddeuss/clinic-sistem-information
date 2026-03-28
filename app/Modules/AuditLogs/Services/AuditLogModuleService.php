<?php

namespace App\Modules\AuditLogs\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AuditLogModuleService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'module' => (string) ($filters['module'] ?? ''),
            'action' => (string) ($filters['action'] ?? ''),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'user' => filled($filters['user'] ?? null) ? (string) $filters['user'] : '',
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
        ];

        return [
            'filters' => $filters,
            'logs' => $this->table($filters),
            'moduleOptions' => AuditLog::query()->distinct()->orderBy('module')->pluck('module'),
            'actionOptions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'userOptions' => User::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return AuditLog::query()
            ->with([
                'user:id,name',
                'branch:id,name,code',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('description', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('module', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('action', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['module'] !== '', fn (Builder $query) => $query->where('module', $filters['module']))
            ->when($filters['action'] !== '', fn (Builder $query) => $query->where('action', $filters['action']))
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['user'] !== '', fn (Builder $query) => $query->where('user_id', $filters['user']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('created_at', $filters['date']))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }
}
