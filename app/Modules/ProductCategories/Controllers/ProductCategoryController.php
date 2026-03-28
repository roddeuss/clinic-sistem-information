<?php

namespace App\Modules\ProductCategories\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use App\Modules\ProductCategories\Requests\ProductCategoryRequest;
use App\Modules\ProductCategories\Services\ProductCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly ProductCategoryService $productCategoryService,
    ) {
    }

    public function index(Request $request)
    {
        return view('modules.product-categories.index', [
            'title' => 'Product Categories',
            ...$this->productCategoryService->getIndexData($request->query()),
        ]);
    }

    public function store(ProductCategoryRequest $request): RedirectResponse
    {
        $this->productCategoryService->create($request->validated());

        return back()->with('status', 'Product category berhasil ditambahkan.');
    }

    public function update(ProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->productCategoryService->update($productCategory, $request->validated());

        return back()->with('status', 'Product category berhasil diperbarui.');
    }

    public function destroy(ProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $this->productCategoryService->delete($productCategory);

        return back()->with('status', 'Product category berhasil diarsipkan.');
    }
}
