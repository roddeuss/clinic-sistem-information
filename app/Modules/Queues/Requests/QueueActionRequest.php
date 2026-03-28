<?php

namespace App\Modules\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('edit queue management');
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['call', 'serve', 'complete', 'skip', 'cancel'])],
        ];
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
