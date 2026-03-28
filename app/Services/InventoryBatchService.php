<?php

namespace App\Services;

use App\Models\MedicineBatch;
use Illuminate\Validation\ValidationException;

class InventoryBatchService
{
    public function __construct(
        private readonly ReorderPointService $reorderPointService,
    ) {
    }

    public function decreaseAvailable(MedicineBatch $batch, float $quantity, string $errorKey = 'quantity'): MedicineBatch
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                $errorKey => 'Quantity harus lebih besar dari nol.',
            ]);
        }

        $available = (float) $batch->quantity_available;

        if ($quantity > $available) {
            throw ValidationException::withMessages([
                $errorKey => sprintf('Quantity melebihi stok tersedia batch %s. Maksimal %.2f.', $batch->batch_number, $available),
            ]);
        }

        $batch->update([
            'quantity_available' => round($available - $quantity, 2),
        ]);

        $fresh = $batch->fresh();
        $this->reorderPointService->refreshForMedicineBranch((int) $fresh->medicine_id, (int) $fresh->branch_id);

        return $fresh;
    }

    public function increaseAvailable(MedicineBatch $batch, float $quantity, string $errorKey = 'quantity'): MedicineBatch
    {
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                $errorKey => 'Quantity harus lebih besar dari nol.',
            ]);
        }

        $batch->update([
            'quantity_available' => round((float) $batch->quantity_available + $quantity, 2),
        ]);

        $fresh = $batch->fresh();
        $this->reorderPointService->refreshForMedicineBranch((int) $fresh->medicine_id, (int) $fresh->branch_id);

        return $fresh;
    }

    public function setAvailable(MedicineBatch $batch, float $quantity, string $errorKey = 'quantity'): MedicineBatch
    {
        if ($quantity < 0) {
            throw ValidationException::withMessages([
                $errorKey => 'Quantity tidak boleh negatif.',
            ]);
        }

        $batch->update([
            'quantity_available' => round($quantity, 2),
        ]);

        $fresh = $batch->fresh();
        $this->reorderPointService->refreshForMedicineBranch((int) $fresh->medicine_id, (int) $fresh->branch_id);

        return $fresh;
    }
}
