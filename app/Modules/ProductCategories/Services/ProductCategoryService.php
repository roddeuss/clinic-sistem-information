<?php

namespace App\Modules\ProductCategories\Services;

use App\Models\ProductCategory;
use App\Services\AuditLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
class ProductCategoryService
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
            'categories' => $this->table($filters),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): ProductCategory
    {
        $productCategory = ProductCategory::query()->create($this->attributes($payload));

        $this->auditLogService->log(
            'product_categories',
            'created',
            $productCategory,
            'Product category baru dibuat.',
            [],
            $productCategory->fresh()->toArray(),
        );

        return $productCategory;
    }

    public function update(ProductCategory $productCategory, array $payload): ProductCategory
    {
        $before = $productCategory->toArray();
        $productCategory->update($this->attributes($payload));

        $fresh = $productCategory->fresh();

        $this->auditLogService->log(
            'product_categories',
            'updated',
            $productCategory,
            'Product category diperbarui.',
            $before,
            $fresh->toArray(),
        );

        return $fresh;
    }

    public function delete(ProductCategory $productCategory): void
    {
        $before = $productCategory->toArray();

        $productCategory->update([
            'is_active' => false,
        ]);

        $fresh = $productCategory->fresh();

        $this->auditLogService->log(
            'product_categories',
            'archived',
            $fresh,
            'Product category diarsipkan.',
            $before,
            $fresh?->toArray() ?? [],
            [
                'product_category_id' => $productCategory->id,
                'code' => $before['code'] ?? null,
                'name' => $before['name'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return ProductCategory::query()
            ->withCount('medicines')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
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
            'description' => $payload['description'],
            'sort_order' => $payload['sort_order'],
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
