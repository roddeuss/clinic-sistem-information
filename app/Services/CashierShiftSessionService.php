<?php

namespace App\Services;

use App\Models\CashierShift;
use Illuminate\Validation\ValidationException;

class CashierShiftSessionService
{
    private const SESSION_KEY = 'active_cashier_shift_id';

    public function activeShift(): ?CashierShift
    {
        if (! auth()->check()) {
            return null;
        }

        $shiftId = session(self::SESSION_KEY);

        $baseQuery = CashierShift::query()
            ->with([
                'counter:id,branch_id,name,code',
                'branch:id,name,code',
                'user:id,name',
            ])
            ->where('user_id', auth()->id())
            ->where('status', 'open');

        if ($shiftId) {
            $shift = (clone $baseQuery)->find($shiftId);

            if ($shift) {
                return $shift;
            }
        }

        return $baseQuery
            ->orderByDesc('opened_at')
            ->orderByDesc('id')
            ->first();
    }

    public function requireActiveShift(): CashierShift
    {
        $shift = $this->activeShift();

        if (! $shift) {
            throw ValidationException::withMessages([
                'cashier_shift' => 'Buka cashier shift terlebih dahulu sebelum menerima pembayaran.',
            ]);
        }

        return $shift;
    }

    public function setActiveShift(CashierShift $shift): void
    {
        session([self::SESSION_KEY => $shift->id]);
    }

    public function forget(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
