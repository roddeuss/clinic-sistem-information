<?php

namespace App\Modules\Pharmacy\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Modules\Pharmacy\Requests\PharmacyRequest;
use App\Modules\Pharmacy\Services\PharmacyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PharmacyController extends Controller
{
    public function __construct(
        private readonly PharmacyService $pharmacyService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.pharmacy.index', [
            'title' => 'Pharmacy',
            ...$this->pharmacyService->getIndexData($request->query()),
        ]);
    }

    public function storeMedicine(PharmacyRequest $request): RedirectResponse
    {
        $this->pharmacyService->createMedicine($request->validated());

        return back()->with('status', 'Medicine berhasil ditambahkan.');
    }

    public function updateMedicine(PharmacyRequest $request, Medicine $medicine): RedirectResponse
    {
        $this->pharmacyService->updateMedicine($medicine, $request->validated());

        return back()->with('status', 'Medicine berhasil diperbarui.');
    }

    public function destroyMedicine(PharmacyRequest $request, Medicine $medicine): RedirectResponse
    {
        $this->pharmacyService->deleteMedicine($medicine);

        return back()->with('status', 'Medicine berhasil diarsipkan.');
    }

    public function storeBatch(PharmacyRequest $request): RedirectResponse
    {
        $this->pharmacyService->createBatch($request->validated());

        return back()->with('status', 'Batch berhasil ditambahkan.');
    }

    public function updateBatch(PharmacyRequest $request, MedicineBatch $batch): RedirectResponse
    {
        $this->pharmacyService->updateBatch($batch, $request->validated());

        return back()->with('status', 'Batch berhasil diperbarui.');
    }

    public function destroyBatch(PharmacyRequest $request, MedicineBatch $batch): RedirectResponse
    {
        $this->pharmacyService->deleteBatch($batch);

        return back()->with('status', 'Batch berhasil diarsipkan.');
    }
}
