<?php

namespace App\Modules\Queues\Services;

use App\Models\QueueTicket;
use App\Models\Section;
use App\Models\User;
use App\Modules\Queues\Exceptions\QueueManagementException;
use App\Services\ActiveCounterService;
use App\Services\AuditLogService;
use App\Services\QueueBoardService;
use App\Services\QueueService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueManagementService
{
    private const ALLOWED_SORTS = [
        'queue_date',
        'queue_number',
        'status',
        'called_at',
        'completed_at',
    ];

    public function __construct(
        private readonly ActiveCounterService $activeCounterService,
        private readonly QueueService $queueService,
        private readonly QueueBoardService $queueBoardService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $activeCounter = $this->activeCounterService->activeCounter();

        return [
            'filters' => $filters,
            'activeCounter' => $activeCounter,
            'counterOptions' => $this->activeCounterService->options(),
            'queueTickets' => $this->queueTable($filters, $activeCounter?->branch_id),
            'sectionOptions' => $this->sectionOptions($activeCounter?->branch_id),
            ...$this->getBoardPayload($filters['date']),
            'abilities' => $this->abilities(),
            'sortOptions' => $this->sortOptions(),
            'perPageOptions' => [10, 25, 50, 100],
        ];
    }

    public function getBoardPayload(?string $queueDate = null): array
    {
        $activeCounter = $this->activeCounterService->activeCounter();
        $resolvedDate = filled($queueDate) ? (string) $queueDate : now()->toDateString();

        return [
            'activeCounter' => $activeCounter,
            ...$this->queueBoardService->boardData($activeCounter?->branch_id, $resolvedDate),
        ];
    }

    public function callNext(int $sectionId, User $actor): array
    {
        try {
            return DB::transaction(function () use ($sectionId, $actor): array {
                $activeCounter = $this->activeCounterService->requireActiveCounter();
                $section = $this->resolveSection($sectionId, $activeCounter->branch_id);

                $activeSectionQueue = QueueTicket::query()
                    ->where('branch_id', $activeCounter->branch_id)
                    ->where('section_id', $section->getKey())
                    ->whereDate('queue_date', now()->toDateString())
                    ->whereIn('status', ['called', 'in_service'])
                    ->lockForUpdate()
                    ->first();

                if ($activeSectionQueue !== null) {
                    throw new QueueManagementException(
                        'Section ini masih memiliki antrian aktif yang sedang dipanggil atau dilayani.',
                        409,
                    );
                }

                $ticket = QueueTicket::query()
                    ->with($this->indexRelations())
                    ->where('branch_id', $activeCounter->branch_id)
                    ->where('section_id', $section->getKey())
                    ->whereDate('queue_date', now()->toDateString())
                    ->where('status', 'waiting')
                    ->orderBy('queue_number')
                    ->lockForUpdate()
                    ->first();

                if ($ticket === null) {
                    throw new QueueManagementException(
                        'Belum ada antrian waiting untuk section yang dipilih.',
                        409,
                        'section_id',
                    );
                }

                $before = $this->auditSnapshot($ticket);
                $changed = $this->queueService->transition($ticket, 'call');
                $freshTicket = $ticket->fresh($this->indexRelations());

                if ($changed) {
                    $this->auditLogService->log(
                        module: 'queue_management',
                        action: 'call_next',
                        auditable: $freshTicket,
                        description: sprintf('Antrian %s dipanggil dari queue desk oleh %s.', $freshTicket->queue_code, $actor->email),
                        before: $before,
                        after: $this->auditSnapshot($freshTicket),
                        meta: [
                            'actor_user_id' => $actor->getKey(),
                            'branch_id' => $activeCounter->branch_id,
                            'counter_id' => $activeCounter->getKey(),
                            'section_id' => $section->getKey(),
                        ],
                    );
                }

                return [
                    'queue' => $freshTicket,
                    'changed' => $changed,
                ];
            });
        } catch (ValidationException $exception) {
            throw $this->toQueueException($exception);
        }
    }

    public function applyAction(QueueTicket $queueTicket, string $action, User $actor): array
    {
        try {
            return DB::transaction(function () use ($queueTicket, $action, $actor): array {
                $activeCounter = $this->activeCounterService->requireActiveCounter();
                $lockedQueue = QueueTicket::query()
                    ->with($this->indexRelations())
                    ->lockForUpdate()
                    ->findOrFail($queueTicket->getKey());

                $this->guardSameBranchContext($lockedQueue, $activeCounter->branch_id);

                $before = $this->auditSnapshot($lockedQueue);
                $changed = $this->queueService->transition($lockedQueue, $action);
                $freshQueue = $lockedQueue->fresh($this->indexRelations());

                if ($changed) {
                    $this->auditLogService->log(
                        module: 'queue_management',
                        action: 'transition_' . $action,
                        auditable: $freshQueue,
                        description: sprintf('Antrian %s diubah ke aksi %s oleh %s.', $freshQueue->queue_code, strtoupper($action), $actor->email),
                        before: $before,
                        after: $this->auditSnapshot($freshQueue),
                        meta: [
                            'actor_user_id' => $actor->getKey(),
                            'branch_id' => $activeCounter->branch_id,
                            'counter_id' => $activeCounter->getKey(),
                            'action' => $action,
                        ],
                    );
                }

                return [
                    'queue' => $freshQueue,
                    'changed' => $changed,
                ];
            });
        } catch (ValidationException $exception) {
            throw $this->toQueueException($exception);
        }
    }

    public function queuePayload(QueueTicket $queueTicket): array
    {
        $queueTicket->loadMissing($this->indexRelations());

        return [
            'id' => $queueTicket->getKey(),
            'visit_registration_id' => $queueTicket->visit_registration_id,
            'patient_id' => $queueTicket->patient_id,
            'patient_name' => $queueTicket->patient?->full_name,
            'patient_phone' => $queueTicket->patient?->phone,
            'medical_record_no' => $queueTicket->patientBranchRecord?->medical_record_no,
            'branch_id' => $queueTicket->branch_id,
            'section_id' => $queueTicket->section_id,
            'section_name' => $queueTicket->section?->name,
            'doctor_id' => $queueTicket->doctor_id,
            'doctor_name' => $queueTicket->doctor?->displayName(),
            'counter_id' => $queueTicket->counter_id,
            'counter_code' => $queueTicket->counter?->code,
            'counter_name' => $queueTicket->counter?->name,
            'called_by_user_id' => $queueTicket->called_by_user_id,
            'called_by_name' => $queueTicket->calledBy?->name,
            'queue_date' => $queueTicket->queue_date?->toDateString(),
            'queue_number' => $queueTicket->queue_number,
            'queue_code' => $queueTicket->queue_code,
            'status' => $queueTicket->status,
            'room_label' => $queueTicket->visitRegistration?->doctorSchedule?->room_label,
            'called_at' => $queueTicket->called_at?->toIso8601String(),
            'serving_at' => $queueTicket->serving_at?->toIso8601String(),
            'completed_at' => $queueTicket->completed_at?->toIso8601String(),
            'skipped_at' => $queueTicket->skipped_at?->toIso8601String(),
            'cancelled_at' => $queueTicket->cancelled_at?->toIso8601String(),
        ];
    }

    private function queueTable(array $filters, ?int $branchId): LengthAwarePaginator
    {
        return QueueTicket::query()
            ->with($this->indexRelations())
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->searchForManagement($filters['search'])
            ->filterDate($filters['date'])
            ->filterStatus($filters['status'])
            ->filterSection($filters['section'])
            ->orderByAllowed($filters['sort_by'], $filters['sort_direction'], self::ALLOWED_SORTS)
            ->paginate($filters['per_page'])
            ->withQueryString();
    }

    private function sectionOptions(?int $branchId): Collection
    {
        return Section::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name', 'code', 'type']);
    }

    private function resolveSection(int $sectionId, int $branchId): Section
    {
        $section = Section::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->find($sectionId);

        if ($section === null) {
            throw new QueueManagementException('Section aktif tidak ditemukan pada branch counter aktif.', 404, 'section_id');
        }

        return $section;
    }

    private function guardSameBranchContext(QueueTicket $queueTicket, int $activeBranchId): void
    {
        if ($queueTicket->branch_id !== $activeBranchId) {
            throw new QueueManagementException('Antrian ini tidak berada pada branch counter aktif.', 409);
        }
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'view' => $user?->hasRole('super-admin') || ($user?->can('view queue management') ?? false),
            'call_next' => $user?->hasRole('super-admin') || ($user?->can('edit queue management') ?? false),
            'edit' => $user?->hasRole('super-admin') || ($user?->can('edit queue management') ?? false),
        ];
    }

    private function sortOptions(): array
    {
        return [
            'queue_date' => 'Tanggal antrian',
            'queue_number' => 'Nomor antrian',
            'status' => 'Status',
            'called_at' => 'Waktu dipanggil',
            'completed_at' => 'Waktu selesai',
        ];
    }

    private function auditSnapshot(QueueTicket $queueTicket): array
    {
        return [
            'id' => $queueTicket->getKey(),
            'visit_registration_id' => $queueTicket->visit_registration_id,
            'patient_id' => $queueTicket->patient_id,
            'patient_name' => $queueTicket->patient?->full_name,
            'medical_record_no' => $queueTicket->patientBranchRecord?->medical_record_no,
            'branch_id' => $queueTicket->branch_id,
            'section_id' => $queueTicket->section_id,
            'section_name' => $queueTicket->section?->name,
            'doctor_id' => $queueTicket->doctor_id,
            'doctor_name' => $queueTicket->doctor?->displayName(),
            'counter_id' => $queueTicket->counter_id,
            'counter_code' => $queueTicket->counter?->code,
            'called_by_user_id' => $queueTicket->called_by_user_id,
            'called_by_name' => $queueTicket->calledBy?->name,
            'queue_date' => $queueTicket->queue_date?->toDateString(),
            'queue_number' => $queueTicket->queue_number,
            'queue_code' => $queueTicket->queue_code,
            'status' => $queueTicket->status,
            'room_label' => $queueTicket->visitRegistration?->doctorSchedule?->room_label,
            'called_at' => $queueTicket->called_at?->toIso8601String(),
            'serving_at' => $queueTicket->serving_at?->toIso8601String(),
            'completed_at' => $queueTicket->completed_at?->toIso8601String(),
            'skipped_at' => $queueTicket->skipped_at?->toIso8601String(),
            'cancelled_at' => $queueTicket->cancelled_at?->toIso8601String(),
        ];
    }

    private function indexRelations(): array
    {
        return [
            'patient:id,full_name,phone',
            'patientBranchRecord:id,patient_id,branch_id,medical_record_no',
            'section:id,name,code,queue_prefix,type',
            'doctor:id,full_name,title_prefix,title_suffix',
            'counter:id,name,code',
            'calledBy:id,name',
            'visitRegistration:id,doctor_schedule_id',
            'visitRegistration.doctorSchedule:id,room_label',
        ];
    }

    private function toQueueException(ValidationException $exception): QueueManagementException
    {
        $messages = $exception->errors();
        $key = array_key_first($messages) ?: 'queue_management';
        $message = $messages[$key][0] ?? $exception->getMessage();

        return new QueueManagementException($message, 422, (string) $key);
    }
}
