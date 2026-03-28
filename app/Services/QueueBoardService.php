<?php

namespace App\Services;

use App\Models\QueueTicket;
use Illuminate\Support\Collection;

class QueueBoardService
{
    public function boardData(?int $branchId, string $queueDate): array
    {
        if (! $branchId) {
            return [
                'liveBoard' => [],
                'sectionSummaries' => [],
                'realtime' => [
                    'branch_channel' => null,
                    'watched_date' => $queueDate,
                ],
            ];
        }

        $tickets = QueueTicket::query()
            ->with([
                'section:id,name,code,type',
                'doctor:id,full_name,title_prefix,title_suffix',
                'counter:id,name,code',
                'visitRegistration:id,doctor_schedule_id',
                'visitRegistration.doctorSchedule:id,room_label',
            ])
            ->where('branch_id', $branchId)
            ->whereDate('queue_date', $queueDate)
            ->orderBy('section_id')
            ->orderBy('queue_number')
            ->get();

        $liveBoard = $tickets
            ->filter(fn (QueueTicket $ticket): bool => in_array($ticket->status, ['called', 'in_service'], true))
            ->map(fn (QueueTicket $ticket): array => $this->ticketPayload($ticket))
            ->values()
            ->all();

        $sectionSummaries = $tickets
            ->groupBy('section_id')
            ->map(function (Collection $sectionTickets): array {
                /** @var QueueTicket|null $firstTicket */
                $firstTicket = $sectionTickets->first();
                $nextWaiting = $sectionTickets->first(fn (QueueTicket $ticket): bool => $ticket->status === 'waiting');
                $currentServing = $sectionTickets->first(fn (QueueTicket $ticket): bool => in_array($ticket->status, ['called', 'in_service'], true));

                return [
                    'section_id' => $firstTicket?->section_id,
                    'section_name' => $firstTicket?->section?->name ?? '-',
                    'section_type' => $firstTicket?->section?->type ?? 'regular',
                    'waiting_count' => $sectionTickets->where('status', 'waiting')->count(),
                    'called_count' => $sectionTickets->where('status', 'called')->count(),
                    'in_service_count' => $sectionTickets->where('status', 'in_service')->count(),
                    'completed_count' => $sectionTickets->where('status', 'completed')->count(),
                    'next_queue_code' => $nextWaiting?->queue_code,
                    'current_queue_code' => $currentServing?->queue_code,
                    'current_doctor_name' => $currentServing?->doctor?->displayName(),
                    'current_room_label' => $currentServing?->visitRegistration?->doctorSchedule?->room_label,
                ];
            })
            ->sortBy(fn (array $summary) => [$summary['section_type'], $summary['section_name']])
            ->values()
            ->all();

        return [
            'liveBoard' => $liveBoard,
            'sectionSummaries' => $sectionSummaries,
            'realtime' => [
                'branch_channel' => 'queues.branch.' . $branchId,
                'watched_date' => $queueDate,
            ],
        ];
    }

    public function ticketPayload(QueueTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'branch_id' => $ticket->branch_id,
            'section_id' => $ticket->section_id,
            'queue_date' => $ticket->queue_date?->toDateString(),
            'queue_code' => $ticket->queue_code,
            'status' => $ticket->status,
            'section_name' => $ticket->section?->name,
            'section_type' => $ticket->section?->type,
            'doctor_name' => $ticket->doctor?->displayName(),
            'room_label' => $ticket->visitRegistration?->doctorSchedule?->room_label,
            'counter_code' => $ticket->counter?->code,
            'counter_name' => $ticket->counter?->name,
            'called_at' => $ticket->called_at?->toIso8601String(),
            'serving_at' => $ticket->serving_at?->toIso8601String(),
            'completed_at' => $ticket->completed_at?->toIso8601String(),
        ];
    }
}
