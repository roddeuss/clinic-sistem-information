<?php

namespace App\Modules\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueCallNextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit queue management');
    }

    public function rules(): array
    {
        return [
            'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
