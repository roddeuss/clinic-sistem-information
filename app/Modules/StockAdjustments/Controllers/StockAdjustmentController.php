<?php

namespace App\Modules\StockAdjustments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Modules\StockAdjustments\Requests\StockAdjustmentRequest;
use App\Modules\StockAdjustments\Services\StockAdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly StockAdjustmentService $stockAdjustmentService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.stock-adjustments.index', [
            'title' => 'Stock Adjustments',
            ...$this->stockAdjustmentService->getIndexData($request->query()),
        ]);
    }

    public function store(StockAdjustmentRequest $request): RedirectResponse
    {
        $this->stockAdjustmentService->create($request->validated());

        return back()->with('status', 'Stock adjustment berhasil ditambahkan.');
    }

    public function update(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->stockAdjustmentService->update($stockAdjustment, $request->validated());

        return back()->with('status', 'Stock adjustment berhasil diperbarui.');
    }

    public function apply(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->stockAdjustmentService->apply($stockAdjustment);

        return back()->with('status', 'Stock adjustment berhasil diterapkan.');
    }

    public function cancel(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->stockAdjustmentService->cancel($stockAdjustment, $request->validated());

        return back()->with('status', 'Stock adjustment berhasil dibatalkan.');
    }

    public function destroy(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->stockAdjustmentService->delete($stockAdjustment);

        return back()->with('status', 'Stock adjustment draft berhasil diarsipkan.');
    }
}
