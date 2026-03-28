<?php

namespace App\Modules\Laboratory\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LaboratoryOrderPrintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view laboratory management');
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
