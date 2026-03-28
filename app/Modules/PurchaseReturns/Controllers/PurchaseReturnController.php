<?php

namespace App\Modules\PurchaseReturns\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Modules\PurchaseReturns\Requests\PurchaseReturnRequest;
use App\Modules\PurchaseReturns\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly PurchaseReturnService $purchaseReturnService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.purchase-returns.index', [
            'title' => 'Purchase Returns',
            ...$this->purchaseReturnService->getIndexData($request->query()),
        ]);
    }

    public function store(PurchaseReturnRequest $request): RedirectResponse
    {
        $this->purchaseReturnService->create($request->validated());

        return back()->with('status', 'Purchase return berhasil ditambahkan.');
    }

    public function update(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->purchaseReturnService->update($purchaseReturn, $request->validated());

        return back()->with('status', 'Purchase return berhasil diperbarui.');
    }

    public function complete(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->purchaseReturnService->complete($purchaseReturn);

        return back()->with('status', 'Purchase return berhasil diselesaikan.');
    }

    public function cancel(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->purchaseReturnService->cancel($purchaseReturn, $request->validated());

        return back()->with('status', 'Purchase return berhasil dibatalkan.');
    }

    public function destroy(PurchaseReturnRequest $request, PurchaseReturn $purchaseReturn): RedirectResponse
    {
        $this->purchaseReturnService->delete($purchaseReturn);

        return back()->with('status', 'Purchase return draft berhasil diarsipkan.');
    }
}
