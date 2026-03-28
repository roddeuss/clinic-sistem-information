<?php

namespace App\Modules\AuditLogs\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [];
    }
}
