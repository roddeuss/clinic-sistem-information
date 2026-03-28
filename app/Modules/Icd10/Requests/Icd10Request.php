<?php

namespace App\Modules\Icd10\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class Icd10Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $icd10Id = $this->route('icd10')?->id;

        return [
            'chapter_code' => ['nullable', 'string', 'max:10'],
            'code' => ['required', 'string', 'max:20', Rule::unique('icd10_codes', 'code')->ignore($icd10Id)],
            'name_en' => ['required', 'string', 'max:255'],
            'name_id' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'chapter_code' => filled($this->input('chapter_code')) ? trim((string) $this->input('chapter_code')) : null,
            'code' => strtoupper(trim((string) $this->input('code'))),
            'name_en' => trim((string) $this->input('name_en')),
            'name_id' => trim((string) $this->input('name_id')),
            'description' => filled($this->input('description')) ? trim((string) $this->input('description')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
