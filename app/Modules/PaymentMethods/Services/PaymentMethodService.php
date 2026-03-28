<?php

namespace App\Modules\PaymentMethods\Services;

use App\Models\PaymentMethod;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
class PaymentMethodService
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
            'type' => (string) ($filters['type'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'paymentMethods' => $this->table($filters),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): PaymentMethod
    {
        $paymentMethod = PaymentMethod::query()->create($this->attributes($payload));

        $this->auditLogService->log(
            'payment_methods',
            'created',
            $paymentMethod,
            'Payment method baru dibuat.',
            [],
            $paymentMethod->fresh()->toArray(),
        );

        return $paymentMethod;
    }

    public function update(PaymentMethod $paymentMethod, array $payload): PaymentMethod
    {
        $before = $paymentMethod->toArray();
        $paymentMethod->update($this->attributes($payload));

        $fresh = $paymentMethod->fresh();

        $this->auditLogService->log(
            'payment_methods',
            'updated',
            $paymentMethod,
            'Payment method diperbarui.',
            $before,
            $fresh->toArray(),
        );

        return $fresh;
    }

    public function delete(PaymentMethod $paymentMethod): void
    {
        $before = $paymentMethod->toArray();

        $paymentMethod->update([
            'is_active' => false,
        ]);

        $fresh = $paymentMethod->fresh();

        $this->auditLogService->log(
            'payment_methods',
            'archived',
            $fresh,
            'Payment method diarsipkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'payment_method_id' => $paymentMethod->id,
                'code' => $before['code'] ?? null,
                'name' => $before['name'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return PaymentMethod::query()
            ->withCount('invoices')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->when($filters['type'] !== '', fn (Builder $query) => $query->where('type', $filters['type']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    private function attributes(array $payload): array
    {
        return [
            'code' => strtoupper(trim($payload['code'])),
            'name' => trim($payload['name']),
            'type' => $payload['type'],
            'description' => $payload['description'],
            'is_cash' => $payload['is_cash'],
            'is_active' => $payload['is_active'],
            'sort_order' => $payload['sort_order'],
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
