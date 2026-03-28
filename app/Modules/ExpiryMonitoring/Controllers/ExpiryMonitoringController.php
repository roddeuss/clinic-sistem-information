<?php

namespace App\Modules\ExpiryMonitoring\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MedicineBatch;
use App\Modules\ExpiryMonitoring\Requests\ExpiryMonitoringRequest;
use App\Modules\ExpiryMonitoring\Services\ExpiryMonitoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ExpiryMonitoringController extends Controller
{
    public function __construct(
        private readonly ExpiryMonitoringService $expiryMonitoringService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.expiry-monitoring.index', [
            'title' => 'Expiry Monitoring',
            ...$this->expiryMonitoringService->getIndexData($request->query()),
        ]);
    }

    public function update(ExpiryMonitoringRequest $request, MedicineBatch $batch): RedirectResponse
    {
        $this->expiryMonitoringService->updateBatchStatus($batch, $request->validated());

        return back()->with('status', 'Status expiry monitoring batch berhasil diperbarui.');
    }
}
