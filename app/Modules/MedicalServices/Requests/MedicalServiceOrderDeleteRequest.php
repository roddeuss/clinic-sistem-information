<?php

namespace App\Modules\MedicalServices\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MedicalServiceOrderDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('delete medical service management');
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
