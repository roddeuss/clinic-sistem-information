<?php

namespace App\Modules\ClinicSettings\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Modules\ClinicSettings\Requests\ClinicSettingsRequest;
use App\Modules\ClinicSettings\Services\ClinicSettingsService;
use Illuminate\Http\Request;

class ClinicSettingsController extends Controller
{
    public function __construct(
        private readonly ClinicSettingsService $clinicSettingsService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.clinic-settings.edit', [
            'title' => 'Clinic & Branch Settings',
            ...$this->clinicSettingsService->getIndexData($request->only([
                'search',
                'status',
            ])),
        ]);
    }

    public function updateProfile(ClinicSettingsRequest $request)
    {
        $this->clinicSettingsService->updateClinic($request->validated());

        return redirect()
            ->route('clinic')
            ->with('status', 'Profil klinik berhasil diperbarui.');
    }

    public function storeBranch(ClinicSettingsRequest $request)
    {
        $this->clinicSettingsService->createBranch($request->validated());

        return redirect()
            ->route('clinic')
            ->with('status', 'Cabang baru berhasil ditambahkan.');
    }

    public function updateBranch(ClinicSettingsRequest $request, Branch $branch)
    {
        $this->clinicSettingsService->updateBranch($branch, $request->validated());

        return redirect()
            ->route('clinic')
            ->with('status', 'Cabang berhasil diperbarui.');
    }

    public function destroyBranch(ClinicSettingsRequest $request, Branch $branch)
    {
        $this->clinicSettingsService->deleteBranch($branch);

        return redirect()
            ->route('clinic')
            ->with('status', 'Cabang berhasil diarsipkan.');
    }
}
