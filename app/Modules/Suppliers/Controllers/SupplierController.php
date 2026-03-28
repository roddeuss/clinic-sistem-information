<?php

namespace App\Modules\Suppliers\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Modules\Suppliers\Requests\SupplierRequest;
use App\Modules\Suppliers\Services\SupplierService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function __construct(
        private readonly SupplierService $supplierService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.suppliers.index', [
            'title' => 'Suppliers',
            ...$this->supplierService->getIndexData($request->query()),
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $this->supplierService->create($request->validated());

        return back()->with('status', 'Supplier berhasil ditambahkan.');
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->update($supplier, $request->validated());

        return back()->with('status', 'Supplier berhasil diperbarui.');
    }

    public function destroy(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->supplierService->delete($supplier);

        return back()->with('status', 'Supplier berhasil diarsipkan.');
    }
}
