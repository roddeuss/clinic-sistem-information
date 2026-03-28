<?php

namespace App\Modules\DoctorSchedules\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DoctorScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return match (true) {
            $this->routeIs(
                'doctor-schedules.store',
                'doctor-schedules.update',
                'doctor-schedules.delete',
                'doctor-leaves.store',
                'doctor-leaves.update',
                'doctor-leaves.delete',
            ) => $this->user() !== null,
            default => false,
        };
    }

    public function rules(): array
    {
        return match (true) {
            $this->routeIs('doctor-schedules.store', 'doctor-schedules.update') => [
                'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')],
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'section_id' => ['required', 'integer', Rule::exists('sections', 'id')],
                'day_of_week' => ['required', 'integer', 'between:1,7'],
                'start_time' => ['required', 'date_format:H:i'],
                'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
                'slot_duration_minutes' => ['required', 'integer', 'min:5', 'max:240'],
                'max_patients' => ['required', 'integer', 'min:1', 'max:500'],
                'room_label' => ['nullable', 'string', 'max:80'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
            ],
            $this->routeIs('doctor-leaves.store', 'doctor-leaves.update') => [
                'doctor_id' => ['required', 'integer', Rule::exists('doctors', 'id')],
                'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')],
                'leave_date' => ['required', 'date'],
                'leave_type' => ['required', Rule::in(['full_day', 'partial_time'])],
                'start_time' => ['nullable', 'date_format:H:i', 'required_if:leave_type,partial_time'],
                'end_time' => ['nullable', 'date_format:H:i', 'required_if:leave_type,partial_time', 'after:start_time'],
                'notes' => ['nullable', 'string', 'max:1000'],
                'is_active' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('doctor-schedules.store', 'doctor-schedules.update')) {
            $this->merge([
                'room_label' => $this->input('room_label') ?: null,
                'notes' => $this->input('notes') ?: null,
                'is_active' => $this->boolean('is_active'),
            ]);
        }

        if ($this->routeIs('doctor-leaves.store', 'doctor-leaves.update')) {
            $leaveType = (string) $this->input('leave_type');

            $this->merge([
                'start_time' => $leaveType === 'full_day' ? null : ($this->input('start_time') ?: null),
                'end_time' => $leaveType === 'full_day' ? null : ($this->input('end_time') ?: null),
                'notes' => $this->input('notes') ?: null,
                'is_active' => $this->boolean('is_active'),
            ]);
        }
    }
}
