<?php

namespace App\Modules\Billing\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Billing\Requests\BillingRequest;
use App\Modules\Billing\Services\BillingModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        private readonly BillingModuleService $billingModuleService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.billing.index', [
            'title' => 'Sales Invoices',
            ...$this->billingModuleService->getIndexData($request->query()),
        ]);
    }

    public function refresh(BillingRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->billingModuleService->refresh($invoice);

        return back()->with('status', 'Invoice berhasil disinkronkan ulang.');
    }

    public function pay(BillingRequest $request, Invoice $invoice): RedirectResponse
    {
        $invoice = $this->billingModuleService->pay($invoice, $request->validated());

        return back()->with('status', $invoice->status === 'paid'
            ? 'Invoice berhasil ditandai paid.'
            : 'Pembayaran cicilan invoice berhasil dicatat.');
    }

    public function tempo(BillingRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->billingModuleService->openReceivable($invoice, $request->validated());

        return back()->with('status', 'Invoice berhasil dibuka sebagai receivable tempo.');
    }

    public function print(BillingRequest $request, Invoice $invoice)
    {
        return view('modules.billing.print', [
            'title' => 'Invoice Print',
            'invoice' => $this->billingModuleService->markPrinted($invoice),
        ]);
    }

    public function receipt(BillingRequest $request, Invoice $invoice)
    {
        return view('modules.billing.receipt', [
            'title' => 'Payment Receipt',
            'invoice' => $this->billingModuleService->markReceiptPrinted($invoice),
        ]);
    }

    public function void(BillingRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->billingModuleService->void($invoice, $request->validated());

        return back()->with('status', 'Invoice berhasil di-void.');
    }
}
