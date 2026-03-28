<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\DoctorLetter;
use App\Models\PatientReferral;

class BranchDocumentNumberService
{
    public function nextReferralNumber(Branch $branch): string
    {
        $prefix = sprintf('REF-%s-%s-', strtoupper($branch->code), now()->format('Ym'));

        return $prefix . $this->nextSequence(
            PatientReferral::query()->where('referral_no', 'like', $prefix . '%')->lockForUpdate()->pluck('referral_no')->all(),
        );
    }

    public function nextDoctorLetterNumber(Branch $branch, string $letterType): string
    {
        $typeCode = match ($letterType) {
            'sick_note' => 'SKT',
            'fit_note' => 'SHT',
            'control_note' => 'KTR',
            default => 'LTR',
        };

        $prefix = sprintf('LTR-%s-%s-%s-', strtoupper($branch->code), $typeCode, now()->format('Ym'));

        return $prefix . $this->nextSequence(
            DoctorLetter::query()->where('letter_no', 'like', $prefix . '%')->lockForUpdate()->pluck('letter_no')->all(),
        );
    }

    /**
     * @param  array<int, string|null>  $numbers
     */
    private function nextSequence(array $numbers): string
    {
        $lastNumber = collect($numbers)
            ->filter()
            ->map(function (string $number): int {
                preg_match('/(\d+)$/', $number, $matches);

                return (int) ($matches[1] ?? 0);
            })
            ->max() ?? 0;

        return str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
