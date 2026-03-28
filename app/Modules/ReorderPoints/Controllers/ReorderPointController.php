<?php

namespace App\Modules\ReorderPoints\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MedicineReorderPolicy;
use App\Modules\ReorderPoints\Requests\ReorderPointRequest;
use App\Services\ReorderPointService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReorderPointController extends Controller
{
    public function __construct(
        private readonly ReorderPointService $reorderPointService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.reorder-points.index', [
            'title' => 'Reorder Points',
            ...$this->reorderPointService->getModuleData($request->query()),
        ]);
    }

    public function store(ReorderPointRequest $request): RedirectResponse
    {
        $this->reorderPointService->createPolicy($request->validated());

        return back()->with('status', 'Reorder point policy berhasil ditambahkan.');
    }

    public function update(ReorderPointRequest $request, MedicineReorderPolicy $policy): RedirectResponse
    {
        $this->reorderPointService->updatePolicy($policy, $request->validated());

        return back()->with('status', 'Reorder point policy berhasil diperbarui.');
    }

    public function destroy(ReorderPointRequest $request, MedicineReorderPolicy $policy): RedirectResponse
    {
        $this->reorderPointService->archivePolicy($policy);

        return back()->with('status', 'Reorder point policy berhasil diarsipkan.');
    }
}
