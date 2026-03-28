<?php

namespace App\Services;

use App\Models\VisitRegistration;

class ClinicalWorkflowService
{
    public function refreshVisit(VisitRegistration $visit): VisitRegistration
    {
        $visit->loadMissing([
            'medicalRecord',
            'prescription.items.dispenses',
            'visitMedicalServices',
            'visitProcedures',
            'laboratoryOrders',
            'invoice',
        ]);

        $nextStage = $this->determineCareStage($visit);

        if ($visit->care_stage !== $nextStage) {
            $visit->update([
                'care_stage' => $nextStage,
            ]);
        }

        return $visit->fresh([
            'medicalRecord',
            'prescription.items.dispenses',
            'visitMedicalServices',
            'visitProcedures',
            'laboratoryOrders',
            'invoice',
        ]);
    }

    private function determineCareStage(VisitRegistration $visit): string
    {
        if ($visit->care_stage === 'cancelled' || $visit->registration_status === 'cancelled') {
            return 'cancelled';
        }

        $invoice = $visit->invoice;
        if ($invoice && $invoice->status === 'paid') {
            return 'completed';
        }

        $medicalRecord = $visit->medicalRecord;

        if (! $medicalRecord) {
            return $visit->care_stage;
        }

        if (in_array($medicalRecord->status, ['draft', 'reopened', 'reopen_requested'], true)) {
            return 'in_consultation';
        }

        if ($medicalRecord->status !== 'final') {
            return $visit->care_stage;
        }

        if ($this->hasPendingInHousePrescription($visit) || $this->hasPendingMedicalService($visit) || $this->hasPendingProcedure($visit) || $this->hasPendingInternalLab($visit)) {
            return 'awaiting_fulfillment';
        }

        return 'ready_for_checkout';
    }

    private function hasPendingInHousePrescription(VisitRegistration $visit): bool
    {
        $prescription = $visit->prescription;

        if (! $prescription) {
            return false;
        }

        return $prescription->items->contains(function ($item): bool {
            return $item->hasOpenFulfillment();
        });
    }

    private function hasPendingProcedure(VisitRegistration $visit): bool
    {
        return $visit->visitProcedures->contains(fn ($procedure): bool => in_array($procedure->status, ['ordered', 'in_progress'], true));
    }

    private function hasPendingMedicalService(VisitRegistration $visit): bool
    {
        return $visit->visitMedicalServices->contains(fn ($service): bool => $service->status === 'ordered');
    }

    private function hasPendingInternalLab(VisitRegistration $visit): bool
    {
        return $visit->laboratoryOrders
            ->where('provider_type', 'internal')
            ->contains(fn ($order): bool => in_array($order->status, ['ordered', 'sample_collected', 'processing'], true));
    }
}
