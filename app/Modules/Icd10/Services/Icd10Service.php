<?php

namespace App\Modules\Icd10\Services;

use App\Models\Icd10Code;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
class Icd10Service
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'chapter' => trim((string) ($filters['chapter'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'codes' => $this->table($filters),
            'chapterOptions' => Icd10Code::query()
                ->whereNotNull('chapter_code')
                ->distinct()
                ->orderBy('chapter_code')
                ->pluck('chapter_code'),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): void
    {
        Icd10Code::query()->create($payload);
    }

    public function update(Icd10Code $icd10Code, array $payload): void
    {
        $icd10Code->update($payload);
    }

    public function delete(Icd10Code $icd10Code): void
    {
        $icd10Code->update([
            'is_active' => false,
        ]);
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return Icd10Code::query()
            ->withCount('diagnoses')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('code', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name_en', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('name_id', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['chapter'] !== '', fn (Builder $query) => $query->where('chapter_code', $filters['chapter']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('is_active', $filters['status'] === 'active'))
            ->orderBy('code')
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
