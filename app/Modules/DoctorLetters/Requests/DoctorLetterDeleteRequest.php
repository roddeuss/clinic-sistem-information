<?php

namespace App\Modules\DoctorLetters\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DoctorLetterDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('delete doctor letter management');
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
