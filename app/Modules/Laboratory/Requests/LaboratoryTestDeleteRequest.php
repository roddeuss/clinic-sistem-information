<?php

namespace App\Modules\Laboratory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoryTestDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('delete laboratory management');
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
