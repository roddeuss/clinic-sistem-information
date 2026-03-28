<?php

namespace App\Modules\PurchaseOrders\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Modules\PurchaseOrders\Requests\PurchaseOrderRequest;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.purchase-orders.index', [
            'title' => 'Purchase Orders',
            ...$this->purchaseOrderService->getIndexData($request->query()),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $this->purchaseOrderService->create($request->validated());

        return back()->with('status', 'Purchase order berhasil ditambahkan.');
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->update($purchaseOrder, $request->validated());

        return back()->with('status', 'Purchase order berhasil diperbarui.');
    }

    public function submit(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $result = $this->purchaseOrderService->submit($purchaseOrder);

        return back()->with('status', $result->status === 'submitted'
            ? 'Purchase order berhasil disubmit dan menunggu approval.'
            : 'Purchase order berhasil disubmit ke supplier.');
    }

    public function approve(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->approve($purchaseOrder, $request->validated());

        return back()->with('status', 'Purchase order berhasil di-approve dan siap diterima.');
    }

    public function reject(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->reject($purchaseOrder, $request->validated());

        return back()->with('status', 'Purchase order berhasil direject.');
    }

    public function cancel(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->cancel($purchaseOrder, $request->validated());

        return back()->with('status', 'Purchase order berhasil dibatalkan.');
    }

    public function destroy(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->delete($purchaseOrder);

        return back()->with('status', 'Purchase order draft berhasil diarsipkan.');
    }
}
