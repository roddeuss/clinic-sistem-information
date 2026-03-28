<?php

namespace App\Modules\GoodsReceipts\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Modules\GoodsReceipts\Requests\GoodsReceiptRequest;
use App\Modules\GoodsReceipts\Services\GoodsReceiptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $goodsReceiptService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.goods-receipts.index', [
            'title' => 'Goods Receipts',
            ...$this->goodsReceiptService->getIndexData($request->query()),
        ]);
    }

    public function store(GoodsReceiptRequest $request): RedirectResponse
    {
        $this->goodsReceiptService->create($request->validated());

        return redirect()
            ->route('goods-receipts')
            ->with('status', 'Penerimaan barang berhasil ditambahkan.');
    }

    public function update(GoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->goodsReceiptService->update($goodsReceipt, $request->validated());

        return back()->with('status', 'Penerimaan barang berhasil diperbarui.');
    }

    public function cancel(GoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): RedirectResponse
    {
        $this->goodsReceiptService->cancel($goodsReceipt, $request->validated());

        return back()->with('status', 'Penerimaan barang berhasil dibatalkan.');
    }
}
