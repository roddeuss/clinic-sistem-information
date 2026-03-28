<?php

namespace App\Modules\Suppliers\Services;

use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
class SupplierService
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
        ];

        return [
            'filters' => $filters,
            'suppliers' => $this->table($filters),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): Supplier
    {
        $supplier = Supplier::query()->create($this->attributes($payload));

        $this->auditLogService->log(
            'suppliers',
            'created',
            $supplier,
            'Supplier baru dibuat.',
            [],
            $supplier->fresh()->toArray(),
        );

        return $supplier;
    }

    public function update(Supplier $supplier, array $payload): Supplier
    {
        $before = $supplier->toArray();
        $supplier->update($this->attributes($payload));

        $fresh = $supplier->fresh();

        $this->auditLogService->log(
            'suppliers',
            'updated',
            $supplier,
            'Supplier diperbarui.',
            $before,
            $fresh->toArray(),
        );

        return $fresh;
    }

    public function delete(Supplier $supplier): void
    {
        $before = $supplier->toArray();

        $supplier->update([
            'is_active' => false,
        ]);

        $fresh = $supplier->fresh();

        $this->auditLogService->log(
            'suppliers',
            'archived',
            $fresh,
            'Supplier diarsipkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'supplier_id' => $supplier->id,
                'code' => $before['code'] ?? null,
                'name' => $before['name'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount('medicineBatches')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('npwp', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('contact_person', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    private function attributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'name' => trim($payload['name']),
            'contact_person' => $payload['contact_person'],
            'phone' => $payload['phone'],
            'email' => $payload['email'],
            'npwp' => $payload['npwp'],
            'payment_term_days' => $payload['payment_term_days'],
            'address' => $payload['address'],
            'notes' => $payload['notes'],
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
