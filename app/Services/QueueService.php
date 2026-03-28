<?php

namespace App\Services;

use App\Events\QueueUpdated;
use App\Models\QueueSequence;
use App\Models\QueueTicket;
use App\Models\VisitRegistration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    public function createForRegistration(VisitRegistration $registration): QueueTicket
    {
        $ticket = DB::transaction(function () use ($registration): QueueTicket {
            $lockedRegistration = VisitRegistration::query()
                ->with(['section', 'patientBranchRecord'])
                ->lockForUpdate()
                ->findOrFail($registration->getKey());

            $existingTicket = $lockedRegistration->queueTicket()
                ->lockForUpdate()
                ->first();

            if ($existingTicket !== null) {
                return $existingTicket;
            }

            $queueDate = $lockedRegistration->visit_date->toDateString();
            $sequence = QueueSequence::query()
                ->where('section_id', $lockedRegistration->section_id)
                ->whereDate('queue_date', $queueDate)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = QueueSequence::query()->create([
                    'section_id' => $lockedRegistration->section_id,
                    'queue_date' => $queueDate,
                    'last_number' => 0,
                ]);

                $sequence = QueueSequence::query()
                    ->whereKey($sequence->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence->increment('last_number');
            $sequence->refresh();

            $number = $sequence->last_number;
            $queueCode = sprintf(
                '%s-%s',
                $lockedRegistration->section->queue_prefix,
                str_pad((string) $number, $lockedRegistration->section->queue_number_padding, '0', STR_PAD_LEFT),
            );

            $ticket = QueueTicket::query()->create([
                'visit_registration_id' => $lockedRegistration->id,
                'patient_id' => $lockedRegistration->patient_id,
                'patient_branch_record_id' => $lockedRegistration->patient_branch_record_id,
                'branch_id' => $lockedRegistration->branch_id,
                'section_id' => $lockedRegistration->section_id,
                'counter_id' => $lockedRegistration->counter_id,
                'doctor_id' => $lockedRegistration->doctor_id,
                'queue_date' => $queueDate,
                'queue_number' => $number,
                'queue_code' => $queueCode,
                'status' => 'waiting',
            ]);

            $lockedRegistration->forceFill([
                'registration_status' => 'queued',
                'queued_at' => $lockedRegistration->queued_at ?? Carbon::now(),
            ])->save();

            return $ticket;
        });

        QueueUpdated::dispatch($this->queueRealtimeTicket($ticket));

        return $ticket;
    }

    public function transition(QueueTicket $queueTicket, string $action): bool
    {
        $result = DB::transaction(function () use ($queueTicket, $action): array {
            $lockedTicket = QueueTicket::query()
                ->with('visitRegistration')
                ->lockForUpdate()
                ->findOrFail($queueTicket->getKey());

            $lockedRegistration = $lockedTicket->visitRegistration()
                ->lockForUpdate()
                ->firstOrFail();

            $payload = match ($action) {
                'call' => $this->buildCallPayload($lockedTicket),
                'serve' => $this->buildServePayload($lockedTicket),
                'complete' => $this->buildCompletePayload($lockedTicket),
                'skip' => $this->buildSkipPayload($lockedTicket),
                'cancel' => $this->buildCancelPayload($lockedTicket),
                default => throw ValidationException::withMessages([
                    'action' => 'Aksi antrian tidak dikenali.',
                ]),
            };

            if (! $payload['changed']) {
                return [
                    'changed' => false,
                    'ticket' => $lockedTicket,
                ];
            }

            $lockedTicket->update($payload['ticket']);
            $lockedRegistration->update($payload['registration']);

            return [
                'changed' => true,
                'ticket' => $lockedTicket->fresh(),
            ];
        });

        if ($result['changed']) {
            QueueUpdated::dispatch($this->queueRealtimeTicket($result['ticket']));
        }

        return $result['changed'];
    }

    private function queueRealtimeTicket(QueueTicket $queueTicket): QueueTicket
    {
        return $queueTicket->fresh([
            'section:id,name,code,type',
            'doctor:id,full_name,title_prefix,title_suffix',
            'counter:id,name,code',
            'visitRegistration:id,doctor_schedule_id',
            'visitRegistration.doctorSchedule:id,room_label',
        ]);
    }

    private function buildCallPayload(QueueTicket $queueTicket): array
    {
        if ($queueTicket->status === 'called') {
            return $this->unchangedPayload();
        }

        if (! in_array($queueTicket->status, ['waiting', 'skipped'], true)) {
            throw ValidationException::withMessages([
                'queue' => 'Hanya antrian waiting atau skipped yang bisa dipanggil.',
            ]);
        }

        $activeQueue = QueueTicket::query()
            ->where('branch_id', $queueTicket->branch_id)
            ->where('section_id', $queueTicket->section_id)
            ->whereDate('queue_date', $queueTicket->queue_date->toDateString())
            ->whereKeyNot($queueTicket->getKey())
            ->whereIn('status', ['called', 'in_service'])
            ->lockForUpdate()
            ->first();

        if ($activeQueue !== null) {
            throw ValidationException::withMessages([
                'queue' => 'Masih ada antrian aktif di section ini yang belum selesai diproses.',
            ]);
        }

        return [
            'changed' => true,
            'ticket' => [
                'status' => 'called',
                'called_at' => now(),
                'serving_at' => null,
                'completed_at' => null,
                'skipped_at' => null,
                'cancelled_at' => null,
                'called_by_user_id' => auth()->id(),
            ],
            'registration' => [
                'registration_status' => 'called',
            ],
        ];
    }

    private function buildServePayload(QueueTicket $queueTicket): array
    {
        if ($queueTicket->status === 'in_service') {
            return $this->unchangedPayload();
        }

        if ($queueTicket->status !== 'called') {
            throw ValidationException::withMessages([
                'queue' => 'Hanya antrian called yang bisa diproses.',
            ]);
        }

        return [
            'changed' => true,
            'ticket' => [
                'status' => 'in_service',
                'serving_at' => now(),
                'completed_at' => null,
                'skipped_at' => null,
                'cancelled_at' => null,
            ],
            'registration' => [
                'registration_status' => 'in_service',
            ],
        ];
    }

    private function buildCompletePayload(QueueTicket $queueTicket): array
    {
        if ($queueTicket->status === 'completed') {
            return $this->unchangedPayload();
        }

        if (! in_array($queueTicket->status, ['called', 'in_service'], true)) {
            throw ValidationException::withMessages([
                'queue' => 'Hanya antrian yang sedang dipanggil atau dilayani yang bisa diselesaikan.',
            ]);
        }

        return [
            'changed' => true,
            'ticket' => [
                'status' => 'completed',
                'completed_at' => now(),
                'cancelled_at' => null,
            ],
            'registration' => [
                'registration_status' => 'completed',
            ],
        ];
    }

    private function buildSkipPayload(QueueTicket $queueTicket): array
    {
        if ($queueTicket->status === 'skipped') {
            return $this->unchangedPayload();
        }

        if (! in_array($queueTicket->status, ['waiting', 'called'], true)) {
            throw ValidationException::withMessages([
                'queue' => 'Hanya antrian waiting atau called yang bisa dilewati.',
            ]);
        }

        return [
            'changed' => true,
            'ticket' => [
                'status' => 'skipped',
                'skipped_at' => now(),
                'serving_at' => null,
                'completed_at' => null,
                'cancelled_at' => null,
            ],
            'registration' => [
                'registration_status' => 'skipped',
            ],
        ];
    }

    private function buildCancelPayload(QueueTicket $queueTicket): array
    {
        if ($queueTicket->status === 'cancelled') {
            return $this->unchangedPayload();
        }

        if ($queueTicket->status === 'completed') {
            throw ValidationException::withMessages([
                'queue' => 'Antrian yang sudah completed tidak bisa dibatalkan.',
            ]);
        }

        return [
            'changed' => true,
            'ticket' => [
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ],
            'registration' => [
                'registration_status' => 'cancelled',
                'cancelled_at' => now(),
            ],
        ];
    }

    private function unchangedPayload(): array
    {
        return [
            'changed' => false,
            'ticket' => [],
            'registration' => [],
        ];
    }
}
