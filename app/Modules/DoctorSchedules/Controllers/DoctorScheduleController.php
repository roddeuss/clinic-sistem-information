<?php

namespace App\Modules\DoctorSchedules\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Modules\DoctorSchedules\Requests\DoctorScheduleRequest;
use App\Modules\DoctorSchedules\Services\DoctorScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    public function __construct(
        private readonly DoctorScheduleService $doctorScheduleService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.doctor-schedules.index', [
            'title' => 'Doctor Schedules',
            ...$this->doctorScheduleService->getIndexData($request->query()),
        ]);
    }

    public function storeSchedule(DoctorScheduleRequest $request): RedirectResponse
    {
        $this->doctorScheduleService->createSchedule($request->validated());

        return back()->with('status', 'Doctor schedule berhasil ditambahkan.');
    }

    public function updateSchedule(DoctorScheduleRequest $request, DoctorSchedule $schedule): RedirectResponse
    {
        $this->doctorScheduleService->updateSchedule($schedule, $request->validated());

        return back()->with('status', 'Doctor schedule berhasil diperbarui.');
    }

    public function destroySchedule(DoctorScheduleRequest $request, DoctorSchedule $schedule): RedirectResponse
    {
        $this->doctorScheduleService->deleteSchedule($schedule);

        return back()->with('status', 'Doctor schedule berhasil diarsipkan.');
    }

    public function storeLeave(DoctorScheduleRequest $request): RedirectResponse
    {
        $this->doctorScheduleService->createLeave($request->validated());

        return back()->with('status', 'Doctor leave berhasil ditambahkan.');
    }

    public function updateLeave(DoctorScheduleRequest $request, DoctorLeave $leave): RedirectResponse
    {
        $this->doctorScheduleService->updateLeave($leave, $request->validated());

        return back()->with('status', 'Doctor leave berhasil diperbarui.');
    }

    public function destroyLeave(DoctorScheduleRequest $request, DoctorLeave $leave): RedirectResponse
    {
        $this->doctorScheduleService->deleteLeave($leave);

        return back()->with('status', 'Doctor leave berhasil diarsipkan.');
    }
}
