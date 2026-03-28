<?php

namespace App\Modules\Doctors\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs('doctors.store', 'doctors.update', 'doctors.delete') => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('doctors.store', 'doctors.update') => [
                'full_name' => ['required', 'string', 'max:160'],
                'title_prefix' => ['nullable', 'string', 'max:30'],
                'title_suffix' => ['nullable', 'string', 'max:60'],
                'specialization' => ['required', 'string', 'max:120'],
                'consultation_fee' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
                'str_number' => [
                    'nullable',
                    'string',
                    'max:60',
                    Rule::unique('doctors', 'str_number')->ignore($this->route('doctor')),
                ],
                'str_expired_at' => ['nullable', 'date'],
                'sip_number' => [
                    'nullable',
                    'string',
                    'max:60',
                    Rule::unique('doctors', 'sip_number')->ignore($this->route('doctor')),
                ],
                'sip_expired_at' => ['nullable', 'date'],
                'phone' => ['nullable', 'string', 'max:30'],
                'email' => [
                    'nullable',
                    'email',
                    'max:120',
                    Rule::unique('doctors', 'email')->ignore($this->route('doctor')),
                ],
                'address' => ['nullable', 'string', 'max:500'],
                'signature_file' => ['nullable', 'image', 'max:2048'],
                'remove_signature' => ['nullable', 'boolean'],
                'sections' => ['nullable', 'array'],
                'sections.*' => ['integer', Rule::exists('sections', 'id')],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('doctors.store', 'doctors.update')) {
            $sections = collect($this->input('sections', []))
                ->filter(fn ($value) => filled($value))
                ->map(fn ($value) => (int) $value)
                ->values()
                ->all();

            $this->merge([
                'title_prefix' => $this->input('title_prefix') ?: null,
                'title_suffix' => $this->input('title_suffix') ?: null,
                'str_number' => $this->input('str_number') ?: null,
                'str_expired_at' => $this->input('str_expired_at') ?: null,
                'sip_number' => $this->input('sip_number') ?: null,
                'sip_expired_at' => $this->input('sip_expired_at') ?: null,
                'phone' => $this->input('phone') ?: null,
                'email' => $this->input('email') ?: null,
                'address' => $this->input('address') ?: null,
                'remove_signature' => $this->boolean('remove_signature'),
                'sections' => $sections,
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
