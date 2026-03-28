<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineBatch;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MedicineBatchAllocatorService
{
    /**
     * @return Collection<int, array{batch: MedicineBatch, quantity: float}>
     */
    public function allocate(Medicine $medicine, int $branchId, float $requiredQuantity): Collection
    {
        if ($requiredQuantity <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Jumlah dispense harus lebih besar dari nol.',
            ]);
        }

        $remaining = $requiredQuantity;
        $allocations = collect();

        $batches = MedicineBatch::query()
            ->where('branch_id', $branchId)
            ->where('medicine_id', $medicine->id)
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->where(function ($query): void {
                $query
                    ->whereNull('expired_at')
                    ->orWhereDate('expired_at', '>=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN expired_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expired_at')
            ->orderBy('received_at')
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $batch->quantity_available;

            if ($available <= 0) {
                continue;
            }

            $taken = min($available, $remaining);

            $allocations->push([
                'batch' => $batch,
                'quantity' => $taken,
            ]);

            $remaining -= $taken;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'dispense' => sprintf('Stok batch untuk %s tidak mencukupi. Kekurangan %.2f %s.', $medicine->name, $remaining, $medicine->base_unit ?: 'unit'),
            ]);
        }

        return $allocations;
    }
}
