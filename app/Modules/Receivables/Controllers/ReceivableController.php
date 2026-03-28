<?php

namespace App\Modules\Receivables\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Receivable;
use App\Modules\Receivables\Requests\ReceivableRequest;
use App\Modules\Receivables\Services\ReceivableService;
use Illuminate\Http\RedirectResponse;

class ReceivableController extends Controller
{
    public function __construct(
        private readonly ReceivableService $receivableService,
    ) {
    }

    public function index(ReceivableRequest $request)
    {
        return view('modules.receivables.index', [
            'title' => 'Receivables',
            ...$this->receivableService->getIndexData($request->query()),
        ]);
    }

    public function extend(ReceivableRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->receivableService->extend($receivable, $request->validated());

        return back()->with('status', 'Receivable berhasil diperpanjang.');
    }

    public function settle(ReceivableRequest $request, Receivable $receivable): RedirectResponse
    {
        $invoice = $this->receivableService->settle($receivable, $request->validated());

        return back()->with('status', $invoice->status === 'paid'
            ? 'Receivable berhasil dilunasi.'
            : 'Pembayaran cicilan receivable berhasil dicatat.');
    }

    public function cancel(ReceivableRequest $request, Receivable $receivable): RedirectResponse
    {
        $this->receivableService->cancel($receivable, $request->validated());

        return back()->with('status', 'Receivable berhasil dibatalkan.');
    }
}
