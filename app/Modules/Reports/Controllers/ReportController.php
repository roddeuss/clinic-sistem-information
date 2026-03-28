<?php

namespace App\Modules\Reports\Controllers;

use App\Exports\ReportDatasetExport;
use App\Http\Controllers\Controller;
use App\Modules\Reports\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    public function index(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']), 403);

        return view('modules.reports.index', [
            'title' => 'Reports',
            ...$this->reportService->getIndexData($request->query()),
        ]);
    }

    public function exportExcel(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']), 403);

        $payload = $this->reportService->exportData($request->query());
        $filename = 'report-' . $payload['dataset']['key'] . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new ReportDatasetExport($payload), $filename);
    }

    public function exportPdf(Request $request)
    {
        abort_unless(auth()->user()?->hasAnyRole(['super-admin', 'clinic-admin']), 403);

        $payload = $this->reportService->exportData($request->query());
        $filename = 'report-' . $payload['dataset']['key'] . '-' . now()->format('Ymd-His') . '.pdf';

        return Pdf::loadView('modules.reports.export', $payload)
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }
}
