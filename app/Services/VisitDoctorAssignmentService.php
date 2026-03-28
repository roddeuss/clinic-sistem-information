<?php

namespace App\Services;

use App\Events\QueueUpdated;
use App\Models\Doctor;
use App\Models\VisitDoctorAssignment;
use App\Models\VisitRegistration;

class VisitDoctorAssignmentService
{
    public function assign(VisitRegistration $visitRegistration, ?Doctor $doctor, ?string $notes = null): void
    {
        $visitRegistration->loadMissing('queueTicket');

        $activeAssignment = $visitRegistration->doctorAssignments()
            ->whereNull('released_at')
            ->latest('assigned_at')
            ->first();

        if (
            $activeAssignment
            && (int) $activeAssignment->doctor_id === (int) $doctor?->id
            && (int) $visitRegistration->doctor_id === (int) $doctor?->id
        ) {
            return;
        }

        if ($activeAssignment) {
            $activeAssignment->update([
                'released_at' => now(),
            ]);
        }

        if ($doctor) {
            VisitDoctorAssignment::query()->create([
                'visit_registration_id' => $visitRegistration->id,
                'doctor_id' => $doctor->id,
                'assigned_by_user_id' => auth()->id(),
                'assigned_at' => now(),
                'notes' => $notes,
            ]);
        }

        $visitRegistration->update([
            'doctor_id' => $doctor?->id,
            'doctor_schedule_id' => $visitRegistration->doctor_id === $doctor?->id
                ? $visitRegistration->doctor_schedule_id
                : null,
        ]);

        $queueTicket = $visitRegistration->queueTicket;

        if ($queueTicket) {
            $queueTicket->update([
                'doctor_id' => $doctor?->id,
            ]);

            QueueUpdated::dispatch($queueTicket->fresh([
                'section:id,name,code,type',
                'doctor:id,full_name,title_prefix,title_suffix',
                'counter:id,name,code',
                'visitRegistration:id,doctor_schedule_id',
                'visitRegistration.doctorSchedule:id,room_label',
            ]));
        }
    }
}
