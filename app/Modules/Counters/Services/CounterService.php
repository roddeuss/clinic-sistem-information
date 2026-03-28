<?php

namespace App\Modules\Counters\Services;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CounterService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'counters' => $this->counterTable($filters),
            'branchOptions' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): void
    {
        Counter::query()->create($this->counterAttributes($payload));
    }

    public function update(Counter $counter, array $payload): void
    {
        $counter->update($this->counterAttributes($payload, $counter));
    }

    public function delete(Counter $counter): void
    {
        $counter->update([
            'is_active' => false,
        ]);
    }

    private function counterAttributes(array $payload, ?Counter $counter = null): array
    {
        $branchId = (int) $payload['branch_id'];
        $name = trim($payload['name']);
        $baseCode = $payload['code'] ?: $this->defaultCode($name);

        return [
            'branch_id' => $branchId,
            'name' => $name,
            'code' => $this->uniqueCode($branchId, $baseCode, $counter?->id),
            'location' => $payload['location'],
            'description' => $payload['description'],
            'sort_order' => $payload['sort_order'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function counterTable(array $filters): LengthAwarePaginator
    {
        return Counter::query()
            ->with('branch:id,name,code')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('location', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($filters): void {
                            $branchQuery
                                ->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('code', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['branch'] !== '', function (Builder $query) use ($filters): void {
                $query->where('branch_id', $filters['branch']);
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    private function defaultCode(string $name): string
    {
        $words = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter();

        if ($words->count() > 1) {
            $code = $words
                ->map(fn (string $word): string => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $word) ?: '', 0, 1)))
                ->implode('');
        } else {
            $code = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: '', 0, 4));
        }

        return Str::limit($code !== '' ? $code : 'CTR', 40, '');
    }

    private function uniqueCode(int $branchId, string $code, ?int $ignoreId = null): string
    {
        $baseCode = Str::upper(Str::limit(trim($code), 40, ''));
        $baseCode = $baseCode !== '' ? $baseCode : 'CTR';
        $candidate = $baseCode;
        $counter = 2;

        while (Counter::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('branch_id', $branchId)
            ->where('code', $candidate)
            ->exists()) {
            $suffix = '-' . $counter;
            $candidate = Str::limit($baseCode, 40 - strlen($suffix), '') . $suffix;
            $counter++;
        }

        return $candidate;
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
