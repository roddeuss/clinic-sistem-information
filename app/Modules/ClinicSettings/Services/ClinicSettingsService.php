<?php

namespace App\Modules\ClinicSettings\Services;

use App\Models\Branch;
use App\Models\Clinic;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClinicSettingsService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'clinic' => $this->ensureClinic(),
            'filters' => $filters,
            'branches' => $this->branchTable($filters),
            'abilities' => $this->abilities(),
        ];
    }

    public function updateClinic(array $payload): void
    {
        $this->ensureClinic()->update($payload['clinic']);
    }

    public function createBranch(array $payload): void
    {
        $clinic = $this->ensureClinic();

        $clinic->branches()->create($this->branchAttributes($payload['branch']));
    }

    public function updateBranch(Branch $branch, array $payload): void
    {
        $branch->update($this->branchAttributes($payload['branch']));
    }

    public function deleteBranch(Branch $branch): void
    {
        $branch->update([
            'is_active' => false,
        ]);
    }

    private function ensureClinic(): Clinic
    {
        return Clinic::query()->firstOrCreate([], [
            'name' => 'CSI Clinic',
        ]);
    }

    private function branchAttributes(array $branchData): array
    {
        return [
            'name' => $branchData['name'],
            'code' => $branchData['code'] ?? null,
            'phone' => $branchData['phone'] ?? null,
            'address' => $branchData['address'] ?? null,
            'opening_time' => $branchData['opening_time'] ?? null,
            'closing_time' => $branchData['closing_time'] ?? null,
            'queue_prefix' => $branchData['queue_prefix'] ?? null,
            'queue_number_padding' => $branchData['queue_number_padding'] ?? 3,
            'is_active' => $branchData['is_active'] ?? false,
        ];
    }

    private function branchTable(array $filters): LengthAwarePaginator
    {
        $clinic = $this->ensureClinic();

        return $clinic->branches()
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('queue_prefix', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('phone', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
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
