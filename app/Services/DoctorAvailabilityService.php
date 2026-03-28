<?php

namespace App\Services;

use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\VisitRegistration;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DoctorAvailabilityService
{
    public function availableSlots(int $branchId, int $sectionId, string $visitDate, ?int $ignoreRegistrationId = null): array
    {
        $date = Carbon::parse($visitDate)->startOfDay();
        $dayOfWeek = (int) $date->dayOfWeekIso;

        $schedules = DoctorSchedule::query()
            ->with(['doctor:id,full_name,title_prefix,title_suffix,is_active', 'section:id,name,type'])
            ->where('branch_id', $branchId)
            ->where('section_id', $sectionId)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->whereHas('doctor', fn ($query) => $query->where('is_active', true))
            ->orderBy('start_time')
            ->get();

        if ($schedules->isEmpty()) {
            return [];
        }

        $leaves = DoctorLeave::query()
            ->where('branch_id', $branchId)
            ->whereDate('leave_date', $date)
            ->where('is_active', true)
            ->whereIn('doctor_id', $schedules->pluck('doctor_id'))
            ->get()
            ->groupBy('doctor_id');

        $bookings = VisitRegistration::query()
            ->whereDate('visit_date', $date)
            ->whereIn('doctor_schedule_id', $schedules->pluck('id'))
            ->whereNotIn('registration_status', ['cancelled'])
            ->when($ignoreRegistrationId, fn ($query) => $query->whereKeyNot($ignoreRegistrationId))
            ->get()
            ->groupBy('doctor_schedule_id');

        return $schedules
            ->flatMap(function (DoctorSchedule $schedule) use ($date, $leaves, $bookings): array {
                return $this->buildScheduleSlots(
                    $schedule,
                    $date,
                    $leaves->get($schedule->doctor_id, collect()),
                    $bookings->get($schedule->id, collect()),
                );
            })
            ->values()
            ->all();
    }

    private function buildScheduleSlots(
        DoctorSchedule $schedule,
        Carbon $date,
        Collection $leaves,
        Collection $bookings,
    ): array {
        $start = Carbon::parse($date->toDateString() . ' ' . $schedule->start_time);
        $end = Carbon::parse($date->toDateString() . ' ' . $schedule->end_time);
        $maxSlotCount = max(0, min(
            (int) floor($start->diffInMinutes($end) / max($schedule->slot_duration_minutes, 1)),
            $schedule->max_patients,
        ));

        $bookedSlotKeys = $bookings
            ->map(fn (VisitRegistration $registration): ?string => $registration->slot_start_time)
            ->filter()
            ->values()
            ->all();

        $slots = [];

        for ($index = 0; $index < $maxSlotCount; $index++) {
            $slotStart = $start->copy()->addMinutes($schedule->slot_duration_minutes * $index);
            $slotEnd = $slotStart->copy()->addMinutes($schedule->slot_duration_minutes);

            if ($slotEnd->gt($end)) {
                break;
            }

            if ($this->isBlockedByLeave($slotStart, $slotEnd, $leaves)) {
                continue;
            }

            $slotKey = $slotStart->format('H:i:s');

            if (in_array($slotKey, $bookedSlotKeys, true)) {
                continue;
            }

            $slots[] = [
                'reference' => implode('|', [
                    $schedule->id,
                    $schedule->doctor_id,
                    $slotStart->format('H:i'),
                    $slotEnd->format('H:i'),
                ]),
                'doctor_schedule_id' => $schedule->id,
                'doctor_id' => $schedule->doctor_id,
                'doctor_name' => $schedule->doctor->displayName(),
                'room_label' => $schedule->room_label,
                'slot_start_time' => $slotStart->format('H:i'),
                'slot_end_time' => $slotEnd->format('H:i'),
                'label' => sprintf(
                    '%s | %s - %s%s',
                    $schedule->doctor->displayName(),
                    $slotStart->format('H:i'),
                    $slotEnd->format('H:i'),
                    $schedule->room_label ? ' | ' . $schedule->room_label : '',
                ),
            ];
        }

        return $slots;
    }

    private function isBlockedByLeave(Carbon $slotStart, Carbon $slotEnd, Collection $leaves): bool
    {
        foreach ($leaves as $leave) {
            if ($leave->leave_type === 'full_day') {
                return true;
            }

            if (! $leave->start_time || ! $leave->end_time) {
                continue;
            }

            $leaveStart = Carbon::parse($slotStart->toDateString() . ' ' . $leave->start_time);
            $leaveEnd = Carbon::parse($slotStart->toDateString() . ' ' . $leave->end_time);

            if ($slotStart->lt($leaveEnd) && $slotEnd->gt($leaveStart)) {
                return true;
            }
        }

        return false;
    }
}
