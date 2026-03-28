<?php

namespace App\Modules\Procedures\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcedureOrderDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('delete procedure management');
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
