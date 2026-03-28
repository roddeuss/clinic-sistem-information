<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class AuditLogService
{
    public function log(
        string $module,
        string $action,
        ?Model $auditable = null,
        ?string $description = null,
        array $before = [],
        array $after = [],
        array $meta = []
    ): void {
        $branchId = data_get($auditable, 'branch_id')
            ?? data_get($auditable, 'branch.id')
            ?? ($meta['branch_id'] ?? null);

        \App\Models\AuditLog::query()->create([
            'user_id' => auth()->id(),
            'branch_id' => $branchId,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'module' => $module,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'before_data' => $before === [] ? null : $before,
            'after_data' => $after === [] ? null : $after,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
