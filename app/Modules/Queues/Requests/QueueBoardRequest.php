<?php

namespace App\Modules\Queues\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QueueBoardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->canAccess('view queue management');
    }

    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
        ];
    }

    public function queueDate(): string
    {
        return (string) ($this->validated('date') ?: now()->toDateString());
    }

    private function canAccess(string $permission): bool
    {
        $user = $this->user();

        return $user?->hasRole('super-admin') || ($user?->can($permission) ?? false);
    }
}
