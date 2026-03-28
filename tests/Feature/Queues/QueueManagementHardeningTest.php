<?php

namespace Tests\Feature\Queues;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Patient;
use App\Models\PatientBranchRecord;
use App\Models\QueueTicket;
use App\Models\Section;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Services\PatientRecordService;
use Carbon\Carbon;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\ClinicSettingsSeeder;
use Database\Seeders\NavigationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QueueManagementHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
        ]);
    }

    public function test_queue_index_requires_queue_view_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('queues'))
            ->assertForbidden();
    }

    public function test_queue_index_json_supports_filter_sort_and_pagination(): void
    {
        $user = User::factory()->create();
        $user->assignRole('clinic-admin');

        $branch = Branch::query()->firstOrFail();

        $waitingQueue = $this->createQueueTicket($branch, [
            'patient_name' => 'Waiting Patient',
            'patient_nik' => '3173000000001001',
            'patient_phone' => '081200000001',
            'queue_number' => 1,
            'queue_code' => 'GEN-001',
            'status' => 'waiting',
        ]);

        $completedQueue = $this->createQueueTicket($branch, [
            'patient_name' => 'Completed Patient',
            'patient_nik' => '3173000000001002',
            'patient_phone' => '081200000002',
            'queue_number' => 2,
            'queue_code' => 'GEN-002',
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('queues', [
            'status' => 'completed',
            'date' => Carbon::today()->toDateString(),
            'sort_by' => 'queue_number',
            'sort_direction' => 'desc',
            'per_page' => 10,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('data.filters.status', 'completed')
            ->assertJsonPath('data.filters.sort_by', 'queue_number')
            ->assertJsonPath('data.filters.sort_direction', 'desc')
            ->assertJsonCount(1, 'data.queues.data')
            ->assertJsonPath('data.queues.data.0.id', $completedQueue->id);

        $this->assertNotSame($waitingQueue->id, $completedQueue->id);
    }

    public function test_call_next_writes_audit_log_and_updates_queue(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch, 'QUEUE-1', 'Counter Queue 1');
        $queue = $this->createQueueTicket($branch, [
            'counter' => $counter,
            'status' => 'waiting',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->postJson(route('queues.call-next'), [
                'section_id' => $queue->section_id,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.changed', true)
            ->assertJsonPath('data.queue.status', 'called');

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'queue_management',
            'action' => 'call_next',
            'auditable_type' => $queue->getMorphClass(),
            'auditable_id' => $queue->id,
        ]);
    }

    public function test_call_next_is_blocked_when_section_has_active_queue(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch, 'QUEUE-2', 'Counter Queue 2');

        $activeQueue = $this->createQueueTicket($branch, [
            'counter' => $counter,
            'queue_number' => 1,
            'queue_code' => 'GEN-001',
            'status' => 'called',
            'called_at' => now(),
        ]);

        $waitingQueue = $this->createQueueTicket($branch, [
            'counter' => $counter,
            'section' => $activeQueue->section,
            'queue_number' => 2,
            'queue_code' => 'GEN-002',
            'status' => 'waiting',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->postJson(route('queues.call-next'), [
                'section_id' => $waitingQueue->section_id,
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.queue_management.0', 'Section ini masih memiliki antrian aktif yang sedang dipanggil atau dilayani.');
    }

    public function test_queue_action_is_idempotent_for_same_transition(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $counter = $this->createCounter($branch, 'QUEUE-3', 'Counter Queue 3');
        $queue = $this->createQueueTicket($branch, [
            'counter' => $counter,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $counter->id])
            ->postJson(route('queues.action', $queue), [
                'action' => 'complete',
            ])
            ->assertOk()
            ->assertJsonPath('data.changed', false)
            ->assertJsonPath('data.queue.status', 'completed');

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('module', 'queue_management')
                ->where('action', 'transition_complete')
                ->count()
        );
    }

    public function test_queue_action_is_blocked_on_different_branch_active_counter(): void
    {
        $user = User::factory()->create();
        $user->assignRole('cashier');

        $branch = Branch::query()->firstOrFail();
        $otherBranch = Branch::query()->create([
            'clinic_id' => $branch->clinic_id,
            'name' => 'Cabang Pembanding',
            'code' => 'CB2',
            'queue_prefix' => 'B',
            'queue_number_padding' => 3,
            'is_active' => true,
        ]);

        $activeCounter = $this->createCounter($branch, 'QUEUE-4', 'Counter Queue 4');
        $otherCounter = $this->createCounter($otherBranch, 'QUEUE-5', 'Counter Queue 5');

        $queue = $this->createQueueTicket($otherBranch, [
            'counter' => $otherCounter,
            'status' => 'waiting',
        ]);

        $this->actingAs($user)
            ->withSession(['active_counter_id' => $activeCounter->id])
            ->postJson(route('queues.action', $queue), [
                'action' => 'cancel',
            ])
            ->assertStatus(409)
            ->assertJsonPath('errors.queue_management.0', 'Antrian ini tidak berada pada branch counter aktif.');
    }

    private function createQueueTicket(Branch $branch, array $overrides = []): QueueTicket
    {
        $counter = $overrides['counter'] ?? $this->createCounter($branch, 'COUNTER-' . fake()->unique()->numberBetween(100, 999), 'Counter Queue');
        $section = $overrides['section'] ?? Section::query()->create([
            'branch_id' => $branch->id,
            'name' => 'General ' . fake()->unique()->numberBetween(1, 999),
            'code' => 'GENERAL-' . fake()->unique()->numberBetween(1, 999),
            'type' => 'regular',
            'queue_prefix' => 'GEN',
            'queue_number_padding' => 3,
            'allow_appointment' => true,
            'allow_walk_in' => true,
            'description' => 'General service',
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $patient = Patient::query()->create([
            'full_name' => $overrides['patient_name'] ?? 'Patient Queue ' . fake()->unique()->numberBetween(1, 999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
            'nik' => $overrides['patient_nik'] ?? (string) fake()->unique()->numberBetween(3173000000001000, 3173999999999999),
            'phone' => $overrides['patient_phone'] ?? '0812' . fake()->unique()->numerify('######'),
            'email' => null,
            'is_active' => true,
        ]);

        $record = app(PatientRecordService::class)->ensureBranchRecord($patient, $branch);

        $registration = VisitRegistration::query()->create([
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'counter_id' => $counter->id,
            'section_id' => $section->id,
            'doctor_id' => null,
            'doctor_schedule_id' => null,
            'visit_date' => Carbon::today()->toDateString(),
            'visit_type' => 'emergency',
            'registration_status' => $overrides['registration_status'] ?? 'queued',
            'care_stage' => 'waiting_doctor',
            'vital_status' => 'pending',
            'booking_code' => null,
            'slot_start_time' => null,
            'slot_end_time' => null,
            'notes' => 'Queue test',
            'checked_in_at' => now(),
            'queued_at' => now(),
        ]);

        $queue = QueueTicket::query()->create([
            'visit_registration_id' => $registration->id,
            'patient_id' => $patient->id,
            'patient_branch_record_id' => $record->id,
            'branch_id' => $branch->id,
            'section_id' => $section->id,
            'counter_id' => $counter->id,
            'doctor_id' => null,
            'queue_date' => Carbon::today()->toDateString(),
            'queue_number' => $overrides['queue_number'] ?? 1,
            'queue_code' => $overrides['queue_code'] ?? 'GEN-001',
            'status' => $overrides['status'] ?? 'waiting',
            'called_at' => $overrides['called_at'] ?? null,
            'serving_at' => $overrides['serving_at'] ?? null,
            'completed_at' => $overrides['completed_at'] ?? null,
            'skipped_at' => $overrides['skipped_at'] ?? null,
            'cancelled_at' => $overrides['cancelled_at'] ?? null,
        ]);

        $registration->forceFill([
            'registration_status' => $this->registrationStatusFromQueue($queue->status),
        ])->save();

        return $queue->fresh([
            'section',
            'counter',
            'patient',
            'patientBranchRecord',
            'visitRegistration',
        ]);
    }

    private function createCounter(Branch $branch, string $code, string $name): Counter
    {
        return Counter::query()->create([
            'branch_id' => $branch->id,
            'name' => $name,
            'code' => $code,
            'location' => 'Lobby',
            'description' => 'Queue desk',
            'sort_order' => 10,
            'is_active' => true,
        ]);
    }

    private function registrationStatusFromQueue(string $status): string
    {
        return match ($status) {
            'called' => 'called',
            'in_service' => 'in_service',
            'completed' => 'completed',
            'skipped' => 'skipped',
            'cancelled' => 'cancelled',
            default => 'queued',
        };
    }
}
