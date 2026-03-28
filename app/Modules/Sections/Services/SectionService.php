<?php

namespace App\Modules\Sections\Services;

use App\Models\Branch;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SectionService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'type' => (string) ($filters['type'] ?? ''),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'sections' => $this->sectionTable($filters),
            'branchOptions' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): void
    {
        Section::query()->create($this->sectionAttributes($payload));
    }

    public function update(Section $section, array $payload): void
    {
        $section->update($this->sectionAttributes($payload, $section));
    }

    public function delete(Section $section): void
    {
        $section->update([
            'is_active' => false,
        ]);
    }

    private function sectionAttributes(array $payload, ?Section $section = null): array
    {
        $branchId = (int) $payload['branch_id'];
        $name = trim($payload['name']);
        $code = $this->uniqueCode($branchId, $name, $section?->id);
        $basePrefix = $payload['queue_prefix'] ?: $this->defaultQueuePrefix($name);

        return [
            'branch_id' => $branchId,
            'name' => $name,
            'code' => $code,
            'type' => $payload['type'],
            'queue_prefix' => $this->uniqueQueuePrefix($branchId, $basePrefix, $section?->id),
            'queue_number_padding' => $payload['queue_number_padding'],
            'allow_appointment' => $payload['allow_appointment'],
            'allow_walk_in' => $payload['allow_walk_in'],
            'description' => $payload['description'] ?: null,
            'sort_order' => $payload['sort_order'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function sectionTable(array $filters): LengthAwarePaginator
    {
        return Section::query()
            ->with('branch:id,name,code')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('queue_prefix', 'like', '%' . $filters['search'] . '%')
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
            ->when($filters['type'] !== '', function (Builder $query) use ($filters): void {
                $query->where('type', $filters['type']);
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    private function uniqueCode(int $branchId, string $name, ?int $ignoreId = null): string
    {
        $baseCode = Str::upper(Str::of($name)->slug('-')->limit(60, '')->value());
        $baseCode = $baseCode !== '' ? $baseCode : 'SECTION';
        $candidate = $baseCode;
        $counter = 2;

        while (Section::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('branch_id', $branchId)
            ->where('code', $candidate)
            ->exists()) {
            $candidate = Str::limit($baseCode, 52, '') . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }

    private function defaultQueuePrefix(string $name): string
    {
        $words = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter();

        if ($words->count() > 1) {
            $prefix = $words
                ->map(fn (string $word): string => Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $word) ?: '', 0, 1)))
                ->implode('');
        } else {
            $prefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: '', 0, 3));
        }

        return Str::limit($prefix !== '' ? $prefix : 'SEC', 10, '');
    }

    private function uniqueQueuePrefix(int $branchId, string $prefix, ?int $ignoreId = null): string
    {
        $basePrefix = Str::upper(Str::limit(trim($prefix), 10, ''));
        $basePrefix = $basePrefix !== '' ? $basePrefix : 'SEC';
        $candidate = $basePrefix;
        $counter = 2;

        while (Section::query()
            ->when($ignoreId, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->where('branch_id', $branchId)
            ->where('queue_prefix', $candidate)
            ->exists()) {
            $suffix = (string) $counter;
            $candidate = Str::limit($basePrefix, 10 - strlen($suffix), '') . $suffix;
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
