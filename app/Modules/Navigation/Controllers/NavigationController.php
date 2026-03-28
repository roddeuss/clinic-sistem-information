<?php

namespace App\Modules\Navigation\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Modules\Navigation\Requests\NavigationRequest;
use App\Modules\Navigation\Services\NavigationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    public function __construct(
        private readonly NavigationService $navigationService,
    ) {
    }

    public function categories(Request $request)
    {
        return view('modules.navigation.categories.index', [
            'title' => 'Menu Categories',
            ...$this->navigationService->getCategoryIndexData($request->query()),
        ]);
    }

    public function menus(Request $request)
    {
        return view('modules.navigation.menus.index', [
            'title' => 'Menu Items',
            ...$this->navigationService->getMenuIndexData($request->query()),
        ]);
    }

    public function storeCategory(NavigationRequest $request): RedirectResponse
    {
        $this->navigationService->createCategory($request->validated());

        return back()->with('status', 'Menu category berhasil ditambahkan.');
    }

    public function updateCategory(NavigationRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->navigationService->updateCategory($category, $request->validated());

        return back()->with('status', 'Menu category berhasil diperbarui.');
    }

    public function destroyCategory(NavigationRequest $request, MenuCategory $category): RedirectResponse
    {
        $this->navigationService->deleteCategory($category);

        return back()->with('status', 'Menu category berhasil diarsipkan.');
    }

    public function storeMenu(NavigationRequest $request): RedirectResponse
    {
        $this->navigationService->createMenu($request->validated());

        return back()->with('status', 'Menu berhasil ditambahkan.');
    }

    public function updateMenu(NavigationRequest $request, Menu $menu): RedirectResponse
    {
        $this->navigationService->updateMenu($menu, $request->validated());

        return back()->with('status', 'Menu berhasil diperbarui.');
    }

    public function destroyMenu(NavigationRequest $request, Menu $menu): RedirectResponse
    {
        $this->navigationService->deleteMenu($menu);

        return back()->with('status', 'Menu berhasil diarsipkan.');
    }
}
