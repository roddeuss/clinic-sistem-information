<?php

namespace App\Modules\CashierShifts\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Counter;
use App\Services\ActiveCounterService;
use App\Services\AuditLogService;
use App\Services\CashierShiftSessionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashierShiftService
{
    public function __construct(
        private readonly ActiveCounterService $activeCounterService,
        private readonly CashierShiftSessionService $cashierShiftSessionService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'branch' => filled($filters['branch'] ?? null) ? (string) $filters['branch'] : '',
            'status' => (string) ($filters['status'] ?? ''),
            'date' => (string) ($filters['date'] ?? now()->toDateString()),
        ];

        return [
            'filters' => $filters,
            'shifts' => $this->table($filters),
            'counterOptions' => $this->activeCounterService->options(),
            'branchOptions' => Branch::query()->orderBy('name')->get(['id', 'name', 'code']),
            'activeShift' => $this->cashierShiftSessionService->activeShift(),
            'abilities' => $this->abilities(),
        ];
    }

    public function open(array $payload): CashierShift
    {
        return DB::transaction(function () use ($payload): CashierShift {
            $user = auth()->user();
            $counter = Counter::query()
                ->with('branch:id,name,code')
                ->where('is_active', true)
                ->findOrFail($payload['counter_id']);

            if (CashierShift::query()->where('user_id', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages([
                    'cashier_shift' => 'User ini masih punya cashier shift yang belum ditutup.',
                ]);
            }

            if (CashierShift::query()->where('counter_id', $counter->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages([
                    'counter_id' => 'Counter ini masih dipakai oleh cashier shift lain yang belum ditutup.',
                ]);
            }

            $shift = CashierShift::query()->create([
                'user_id' => $user->id,
                'counter_id' => $counter->id,
                'branch_id' => $counter->branch_id,
                'shift_code' => $this->nextShiftCode(),
                'status' => 'open',
                'opening_balance' => $payload['opening_balance'],
                'opening_notes' => $payload['opening_notes'] ?? null,
                'opened_at' => now(),
            ]);

            $this->activeCounterService->setActiveCounter($counter);
            $this->cashierShiftSessionService->setActiveShift($shift);

            $this->auditLogService->log(
                'cashier_shifts',
                'opened',
                $shift,
                'Cashier shift dibuka.',
                [],
                $shift->fresh()->toArray(),
            );

            return $shift->fresh(['counter.branch', 'user']);
        });
    }

    public function close(CashierShift $shift, array $payload): CashierShift
    {
        return DB::transaction(function () use ($shift, $payload): CashierShift {
            $shift->loadMissing([
                'invoices.paymentMethod',
                'counter.branch',
                'user',
            ]);

            if (! $shift->isOpen()) {
                throw ValidationException::withMessages([
                    'cashier_shift' => 'Cashier shift ini sudah ditutup.',
                ]);
            }

            $user = auth()->user();
            $isAdmin = $user?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false;

            if (! $isAdmin && (int) $shift->user_id !== (int) auth()->id()) {
                throw ValidationException::withMessages([
                    'cashier_shift' => 'Hanya pemilik shift atau admin yang bisa menutup cashier shift ini.',
                ]);
            }

            $expectedCash = (float) $shift->invoices
                ->filter(fn ($invoice) => $invoice->status === 'paid' && $invoice->paymentMethod?->is_cash)
                ->sum('total_amount');

            $before = $shift->toArray();

            $shift->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closing_balance' => $payload['closing_balance'],
                'expected_cash_total' => $expectedCash,
                'cash_variance' => (float) $payload['closing_balance'] - $expectedCash,
                'closed_by_user_id' => auth()->id(),
                'closing_notes' => $payload['closing_notes'] ?? null,
            ]);

            if ((int) ($this->cashierShiftSessionService->activeShift()?->id ?? 0) === (int) $shift->id) {
                $this->cashierShiftSessionService->forget();
            }

            $this->auditLogService->log(
                'cashier_shifts',
                'closed',
                $shift,
                'Cashier shift ditutup.',
                $before,
                $shift->fresh()->toArray(),
            );

            return $shift->fresh(['counter.branch', 'user', 'closedBy']);
        });
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return CashierShift::query()
            ->with([
                'user:id,name',
                'counter:id,branch_id,name,code',
                'branch:id,name,code',
                'closedBy:id,name',
            ])
            ->withCount('invoices')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('shift_code', 'like', '%' . $filters['search'] . '%')
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('name', 'like', '%' . $filters['search'] . '%'))
                        ->orWhereHas('counter', fn (Builder $counterQuery) => $counterQuery->where('name', 'like', '%' . $filters['search'] . '%')->orWhere('code', 'like', '%' . $filters['search'] . '%'));
                });
            })
            ->when($filters['branch'] !== '', fn (Builder $query) => $query->where('branch_id', $filters['branch']))
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['date'] !== '', fn (Builder $query) => $query->whereDate('opened_at', $filters['date']))
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();
    }

    private function nextShiftCode(): string
    {
        $today = now()->format('Ymd');

        $last = CashierShift::query()
            ->where('shift_code', 'like', 'SHIFT-' . $today . '-%')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $lastNumber = 0;

        if ($last && preg_match('/(\d+)$/', $last->shift_code, $matches) === 1) {
            $lastNumber = (int) $matches[1];
        }

        return sprintf('SHIFT-%s-%04d', $today, $lastNumber + 1);
    }

    private function abilities(): array
    {
        $user = auth()->user();

        return [
            'open' => $user !== null,
            'close' => $user !== null,
            'admin' => $user?->hasAnyRole(['super-admin', 'clinic-admin']) ?? false,
        ];
    }
}
