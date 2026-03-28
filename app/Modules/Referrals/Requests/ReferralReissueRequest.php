<?php

namespace App\Modules\Referrals\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReferralReissueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('issue referral management');
    }

    public function rules(): array
    {
        return [];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
