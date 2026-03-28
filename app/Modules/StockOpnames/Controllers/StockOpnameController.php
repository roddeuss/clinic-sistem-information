<?php

namespace App\Modules\StockOpnames\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StockOpname;
use App\Modules\StockOpnames\Requests\StockOpnameRequest;
use App\Modules\StockOpnames\Services\StockOpnameService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockOpnameController extends Controller
{
    public function __construct(
        private readonly StockOpnameService $stockOpnameService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.stock-opnames.index', [
            'title' => 'Stock Opnames',
            ...$this->stockOpnameService->getIndexData($request->query()),
        ]);
    }

    public function store(StockOpnameRequest $request): RedirectResponse
    {
        $this->stockOpnameService->create($request->validated());

        return back()->with('status', 'Stock opname berhasil ditambahkan.');
    }

    public function update(StockOpnameRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        $this->stockOpnameService->update($stockOpname, $request->validated());

        return back()->with('status', 'Stock opname berhasil diperbarui.');
    }

    public function finalize(StockOpnameRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        $this->stockOpnameService->finalize($stockOpname);

        return back()->with('status', 'Stock opname berhasil difinalkan.');
    }

    public function cancel(StockOpnameRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        $this->stockOpnameService->cancel($stockOpname, $request->validated());

        return back()->with('status', 'Stock opname berhasil dibatalkan.');
    }

    public function destroy(StockOpnameRequest $request, StockOpname $stockOpname): RedirectResponse
    {
        $this->stockOpnameService->delete($stockOpname);

        return back()->with('status', 'Stock opname draft berhasil diarsipkan.');
    }
}
