<?php

namespace App\Modules\Navigation\Services;

use App\Models\Menu;
use App\Models\MenuCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
class NavigationService
{
    public function getCategoryIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'categories' => $this->categoryTable($filters),
            'abilities' => $this->abilities(),
        ];
    }

    public function getMenuIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
            'category' => filled($filters['category'] ?? null) ? (string) $filters['category'] : '',
        ];

        return [
            'filters' => $filters,
            'menus' => $this->menuTable($filters),
            'categoryOptions' => MenuCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'parentMenus' => Menu::query()
                ->whereNull('parent_id')
                ->orderBy('title')
                ->get(['id', 'title']),
            'abilities' => $this->abilities(),
        ];
    }

    public function createCategory(array $payload): void
    {
        MenuCategory::query()->create([
            'name' => $payload['name'],
            'slug' => $this->uniqueSlug(MenuCategory::class, $payload['name']),
            'sort_order' => $payload['sort_order'],
            'is_active' => $payload['is_active'],
        ]);
    }

    public function updateCategory(MenuCategory $category, array $payload): void
    {
        $category->update([
            'name' => $payload['name'],
            'slug' => $payload['name'] === $category->name
                ? $category->slug
                : $this->uniqueSlug(MenuCategory::class, $payload['name'], $category->id),
            'sort_order' => $payload['sort_order'],
            'is_active' => $payload['is_active'],
        ]);
    }

    public function deleteCategory(MenuCategory $category): void
    {
        $category->menus()->update([
            'is_active' => false,
        ]);

        $category->update([
            'is_active' => false,
        ]);
    }

    public function createMenu(array $payload): void
    {
        Menu::query()->create($this->menuAttributes($payload));
    }

    public function updateMenu(Menu $menu, array $payload): void
    {
        if (($payload['parent_id'] ?? null) === $menu->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'Menu tidak boleh menjadi parent untuk dirinya sendiri.',
            ]);
        }

        $menu->update($this->menuAttributes($payload, $menu));
    }

    public function deleteMenu(Menu $menu): void
    {
        $this->archiveMenuBranch($menu);
    }

    private function menuAttributes(array $payload, ?Menu $menu = null): array
    {
        return [
            'menu_category_id' => $payload['menu_category_id'],
            'parent_id' => $payload['parent_id'] ?: null,
            'title' => $payload['title'],
            'slug' => $menu && $payload['title'] === $menu->title
                ? $menu->slug
                : $this->uniqueSlug(Menu::class, $payload['title'], $menu?->id),
            'route_name' => $payload['route_name'] ?: null,
            'icon' => $payload['icon'],
            'permission_name' => $this->resolvePermissionFromDestination($payload['route_name'] ?: null),
            'sort_order' => $payload['sort_order'],
            'is_active' => $payload['is_active'],
        ];
    }

    private function uniqueSlug(string $modelClass, string $value, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value);
        $slug = $baseSlug;
        $counter = 1;

        while ($modelClass::query()
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = sprintf('%s-%d', $baseSlug, $counter);
            $counter++;
        }

        return $slug;
    }

    private function categoryTable(array $filters): LengthAwarePaginator
    {
        return MenuCategory::query()
            ->withCount('menus')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    private function menuTable(array $filters): LengthAwarePaginator
    {
        return Menu::query()
            ->with(['category:id,name', 'parent:id,title'])
            ->withCount('children')
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('title', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('slug', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('route_name', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('icon', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', function (Builder $query) use ($filters): void {
                $query->where('is_active', $filters['status'] === 'active');
            })
            ->when($filters['category'] !== '', function (Builder $query) use ($filters): void {
                $query->where('menu_category_id', $filters['category']);
            })
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate(10)
            ->withQueryString();
    }

    private function abilities(): array
    {
        return [
            'create' => auth()->check(),
            'edit' => auth()->check(),
            'delete' => auth()->check(),
        ];
    }

    private function resolvePermissionFromDestination(?string $destination): ?string
    {
        $normalizedDestination = $this->normalizeDestination($destination);

        return match ($normalizedDestination) {
            '/dashboard' => 'view dashboard',
            '/users' => 'view user management',
            '/roles' => 'view role permission',
            '/clinic' => 'view clinic settings',
            '/counters' => 'view counter management',
            '/sections' => 'view section management',
            '/doctors' => 'view doctor management',
            '/doctor-schedules' => 'view doctor schedule',
            '/patients' => 'view patient management',
            '/visit-registrations' => 'view visit registration',
            '/queues' => 'view queue management',
            '/vital-signs' => 'view vital sign management',
            '/medical-records' => 'view medical record management',
            '/icd10' => 'view icd10 management',
            '/menu-categories', '/menus' => 'view menu management',
            default => null,
        };
    }

    private function normalizeDestination(?string $destination): ?string
    {
        if ($destination === null || $destination === '') {
            return null;
        }

        if (str_starts_with($destination, '/')) {
            return rtrim($destination, '/') ?: '/';
        }

        if (Route::has($destination)) {
            return route($destination, absolute: false);
        }

        return null;
    }

    private function archiveMenuBranch(Menu $menu): void
    {
        $menu->children()->get()->each(function (Menu $child): void {
            $this->archiveMenuBranch($child);
        });

        $menu->update([
            'is_active' => false,
        ]);
    }
}
