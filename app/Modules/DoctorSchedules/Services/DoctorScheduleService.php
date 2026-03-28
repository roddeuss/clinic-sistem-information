<?php

namespace App\Modules\DoctorSchedules\Services;

use App\Models\Branch;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Section;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorScheduleService
{
    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'doctor' => filled($filters['doctor'] ?? null) ? (string) $filters['doctor'] : '',
            'section' => filled($filters['section'] ?? null) ? (string) $filters['section'] : '',
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'schedules' => $this->scheduleTable($filters),
            'leaves' => $this->leaveTable($filters),
            'doctorOptions' => Doctor::query()
                ->orderBy('full_name')
                ->get(),
            'branchOptions' => Branch::query()
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'sectionOptions' => Section::query()
                ->with('branch:id,name,code')
                ->orderBy('branch_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'name', 'code', 'type', 'is_active']),
            'dayOptions' => $this->dayOptions(),
            'leaveTypeOptions' => $this->leaveTypeOptions(),
            'abilities' => $this->abilities(),
        ];
    }

    public function createSchedule(array $payload): void
    {
        $this->guardSchedulePayload($payload);

        DoctorSchedule::query()->create($payload);
    }

    public function updateSchedule(DoctorSchedule $schedule, array $payload): void
    {
        $this->guardSchedulePayload($payload, $schedule);

        $schedule->update($payload);
    }

    public function deleteSchedule(DoctorSchedule $schedule): void
    {
        $schedule->update([
            'is_active' => false,
        ]);
    }

    public function createLeave(array $payload): void
    {
        $payload = $this->normalizedLeavePayload($payload);
        $this->guardLeavePayload($payload);

        DoctorLeave::query()->create($payload);
    }

    public function updateLeave(DoctorLeave $leave, array $payload): void
    {
        $payload = $this->normalizedLeavePayload($payload);
        $this->guardLeavePayload($payload, $leave);

        $leave->update($payload);
    }

    public function deleteLeave(DoctorLeave $leave): void
    {
        $leave->update([
            'is_active' => false,
        ]);
    }

    public function dayOptions(): array
    {
        return [
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
            7 => 'Minggu',
        ];
    }

    public function leaveTypeOptions(): array
    {
        return [
            'full_day' => 'Full Day',
            'partial_time' => 'Partial Time',
        ];
    }

    private function scheduleTable(array $filters): LengthAwarePaginator
    {
        return DoctorSchedule::query()
            ->with([
                'doctor:id,full_name,title_prefix,title_suffix,specialization',
                'branch:id,name,code',
                'section:id,name,code,branch_id',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('notes', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('room_label', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('doctor', function (Builder $doctorQuery) use ($filters): void {
                            $doctorQuery
                                ->where('full_name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('specialization', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('branch', function (Builder $branchQuery) use ($filters): void {
                            $branchQuery
                                ->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('code', 'like', '%' . $filters['search'] . '%');
                        })
                        ->orWhereHas('section', function (Builder $sectionQuery) use ($filters): void {
                            $sectionQuery
                                ->where('name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('code', 'like', '%' . $filters['search'] . '%');
                        });
                });
            })
            ->when($filters['branch'] !== '', function (Builder $query) use ($filters): void {
                $query->where('branch_id', $filters['branch']);
            })
            ->when($filters['doctor'] !== '', function (Builder $query) use ($filters): void {
                $query->where('doctor_id', $filters['doctor']);
            })
            ->when($filters['section'] !== '', function (Builder $query) use ($filters): void {
                $query->where('section_id', $filters['section']);
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(10, ['*'], 'schedules_page')
            ->withQueryString();
    }

    private function leaveTable(array $filters): LengthAwarePaginator
    {
        return DoctorLeave::query()
            ->with([
                'doctor:id,full_name,title_prefix,title_suffix,specialization',
                'branch:id,name,code',
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('notes', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('doctor', function (Builder $doctorQuery) use ($filters): void {
                            $doctorQuery
                                ->where('full_name', 'like', '%' . $filters['search'] . '%')
                                ->orWhere('specialization', 'like', '%' . $filters['search'] . '%');
                        })
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
            ->when($filters['doctor'] !== '', function (Builder $query) use ($filters): void {
                $query->where('doctor_id', $filters['doctor']);
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderByDesc('leave_date')
            ->orderBy('start_time')
            ->paginate(10, ['*'], 'leaves_page')
            ->withQueryString();
    }

    private function guardSchedulePayload(array $payload, ?DoctorSchedule $schedule = null): void
    {
        $doctor = Doctor::query()->findOrFail($payload['doctor_id']);

        $sectionBelongsToBranch = Section::query()
            ->whereKey($payload['section_id'])
            ->where('branch_id', $payload['branch_id'])
            ->exists();

        if (! $sectionBelongsToBranch) {
            throw ValidationException::withMessages([
                'section_id' => 'Section yang dipilih tidak berada pada branch tersebut.',
            ]);
        }

        $doctorAssignedToSection = $doctor->sections()
            ->where('sections.id', $payload['section_id'])
            ->exists();

        if (! $doctorAssignedToSection) {
            throw ValidationException::withMessages([
                'section_id' => 'Doctor belum terhubung ke section tersebut. Atur relasi section di Doctor Management dulu.',
            ]);
        }

        $hasOverlap = DoctorSchedule::query()
            ->when($schedule, fn (Builder $query) => $query->whereKeyNot($schedule->id))
            ->where('doctor_id', $payload['doctor_id'])
            ->where('day_of_week', $payload['day_of_week'])
            ->where('start_time', '<', $payload['end_time'])
            ->where('end_time', '>', $payload['start_time'])
            ->exists();

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_time' => 'Jadwal doctor bentrok dengan schedule lain pada hari dan jam yang sama.',
            ]);
        }
    }

    private function normalizedLeavePayload(array $payload): array
    {
        if ($payload['leave_type'] === 'full_day') {
            $payload['start_time'] = null;
            $payload['end_time'] = null;
        }

        return $payload;
    }

    private function guardLeavePayload(array $payload, ?DoctorLeave $leave = null): void
    {
        $query = DoctorLeave::query()
            ->when($leave, fn (Builder $builder) => $builder->whereKeyNot($leave->id))
            ->where('doctor_id', $payload['doctor_id'])
            ->where('branch_id', $payload['branch_id'])
            ->whereDate('leave_date', $payload['leave_date']);

        $hasConflict = false;

        if ($payload['leave_type'] === 'full_day') {
            $hasConflict = $query->exists();
        } else {
            $hasConflict = $query
                ->where(function (Builder $builder) use ($payload): void {
                    $builder
                        ->where('leave_type', 'full_day')
                        ->orWhere(function (Builder $timeBuilder) use ($payload): void {
                            $timeBuilder
                                ->where('start_time', '<', $payload['end_time'])
                                ->where('end_time', '>', $payload['start_time']);
                        });
                })
                ->exists();
        }

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'leave_date' => 'Cuti doctor bentrok dengan data cuti lain pada tanggal atau jam yang sama.',
            ]);
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
