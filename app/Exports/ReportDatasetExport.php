<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReportDatasetExport implements FromView, ShouldAutoSize
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function view(): View
    {
        return view('modules.reports.export', $this->payload);
    }
}
