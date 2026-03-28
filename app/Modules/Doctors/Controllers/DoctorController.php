<?php

namespace App\Modules\Doctors\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Modules\Doctors\Requests\DoctorRequest;
use App\Modules\Doctors\Services\DoctorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function __construct(
        private readonly DoctorService $doctorService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.doctors.index', [
            'title' => 'Doctors',
            ...$this->doctorService->getIndexData($request->query()),
        ]);
    }

    public function store(DoctorRequest $request): RedirectResponse
    {
        $this->doctorService->create([
            ...$request->validated(),
            'signature_file' => $request->file('signature_file'),
        ]);

        return back()->with('status', 'Doctor berhasil ditambahkan.');
    }

    public function update(DoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->doctorService->update($doctor, [
            ...$request->validated(),
            'signature_file' => $request->file('signature_file'),
        ]);

        return back()->with('status', 'Doctor berhasil diperbarui.');
    }

    public function destroy(DoctorRequest $request, Doctor $doctor): RedirectResponse
    {
        $this->doctorService->delete($doctor);

        return back()->with('status', 'Doctor berhasil diarsipkan.');
    }
}
