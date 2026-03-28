<?php

namespace App\Modules\Notifications\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NotificationCenterRequest extends FormRequest
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
