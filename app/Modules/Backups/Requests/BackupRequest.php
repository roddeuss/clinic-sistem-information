<?php

namespace App\Modules\Backups\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super-admin') === true;
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('backups.store') => [
                'notes' => ['nullable', 'string', 'max:1000'],
            ],
            $this->routeIs('backups.restore') => [
                'restore_reason' => ['required', 'string', 'max:1000'],
            ],
            $this->routeIs('backups.delete') => [
                'archive_reason' => ['nullable', 'string', 'max:1000'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => $this->input('notes') ?: null,
            'restore_reason' => $this->input('restore_reason') ?: null,
            'archive_reason' => $this->input('archive_reason') ?: null,
        ]);
    }
}
