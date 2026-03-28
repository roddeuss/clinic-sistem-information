<?php

namespace App\Modules\Doctors\Services;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class DoctorService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'section' => filled($filters['section'] ?? null) ? (string) $filters['section'] : '',
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'doctors' => $this->doctorTable($filters),
            'branchOptions' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'sectionOptions' => Section::query()
                ->with('branch:id,name,code')
                ->orderBy('branch_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'type', 'is_active']),
            'sectionGroups' => Branch::query()
                ->with([
                    'sections' => fn ($query) => $query
                        ->select('sections.id', 'sections.branch_id', 'sections.name', 'sections.code', 'sections.type', 'sections.is_active')
                        ->orderBy('sort_order')
                        ->orderBy('name'),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->filter(fn (Branch $branch): bool => $branch->sections->isNotEmpty())
                ->values(),
            'abilities' => $this->abilities(),
        ];
    }

    public function create(array $payload): void
    {
        DB::transaction(function () use ($payload): void {
            $doctor = Doctor::query()->create($this->doctorAttributes($payload));
            $this->syncSignature($doctor, $payload);
            $doctor->sections()->sync($payload['sections'] ?? []);
        });
    }

    public function update(Doctor $doctor, array $payload): void
    {
        DB::transaction(function () use ($doctor, $payload): void {
            $doctor->update($this->doctorAttributes($payload));
            $this->syncSignature($doctor, $payload);
            $doctor->sections()->sync($payload['sections'] ?? []);
        });
    }

    public function delete(Doctor $doctor): void
    {
        $doctor->update([
            'is_active' => false,
        ]);
    }

    private function doctorTable(array $filters): LengthAwarePaginator
    {
        return Doctor::query()
            ->with([
                'sections' => fn ($query) => $query
                    ->select('sections.id', 'sections.branch_id', 'sections.name', 'sections.code', 'sections.type', 'sections.is_active')
                    ->with('branch:id,name,code'),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('full_name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('title_prefix', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('title_suffix', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('specialization', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('str_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('sip_number', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('phone', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('email', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('sections', function (Builder $sectionQuery) use ($filters): void {
                            $sectionQuery
                                ->where('sections.name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('sections.code', 'like', '%' . $filters['search'] . '%')
                                ->orWhereHas('branch', function (Builder $branchQuery) use ($filters): void {
                                    $branchQuery
                                        ->where('name', 'like', '%' . $filters['search'] . '%')
                                        ->orWhere('code', 'like', '%' . $filters['search'] . '%');
                                });
                        });
                });
            })
            ->when($filters['branch'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('sections', function (Builder $sectionQuery) use ($filters): void {
                    $sectionQuery->where('sections.branch_id', $filters['branch']);
                });
            })
            ->when($filters['section'] !== '', function (Builder $query) use ($filters): void {
                $query->whereHas('sections', function (Builder $sectionQuery) use ($filters): void {
                    $sectionQuery->where('sections.id', $filters['section']);
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('full_name')
            ->paginate(10)
            ->withQueryString();
    }

    private function doctorAttributes(array $payload): array
    {
        return [
            'full_name' => trim($payload['full_name']),
            'title_prefix' => $payload['title_prefix'],
            'title_suffix' => $payload['title_suffix'],
            'specialization' => trim($payload['specialization']),
            'consultation_fee' => $payload['consultation_fee'],
            'str_number' => $payload['str_number'],
            'str_expired_at' => $payload['str_expired_at'],
            'sip_number' => $payload['sip_number'],
            'sip_expired_at' => $payload['sip_expired_at'],
            'phone' => $payload['phone'],
            'email' => $payload['email'],
            'address' => $payload['address'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function syncSignature(Doctor $doctor, array $payload): void
    {
        $signatureFile = $payload['signature_file'] ?? null;

        if ($signatureFile instanceof UploadedFile) {
            if ($doctor->signature_path) {
                Storage::disk('public')->delete($doctor->signature_path);
            }

            $doctor->forceFill([
                'signature_path' => $signatureFile->store('doctor-signatures', 'public'),
            ])->save();

            return;
        }

        if (($payload['remove_signature'] ?? false) && $doctor->signature_path) {
            Storage::disk('public')->delete($doctor->signature_path);

            $doctor->forceFill([
                'signature_path' => null,
            ])->save();
        }
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
