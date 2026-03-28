<?php

namespace App\Modules\Access\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Exceptions\RoleManagementException;
use App\Modules\Access\Requests\RoleManagementIndexRequest;
use App\Modules\Access\Requests\RoleManagementRequest;
use App\Modules\Access\Services\RoleManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function __construct(
        private readonly RoleManagementService $roleManagementService,
    ) {
    }

    public function index(RoleManagementIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Role & Permission',
            ...$this->roleManagementService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Role management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'roles' => $data['roles']->through(fn (Role $role): array => $this->roleManagementService->rolePayload($role)),
                ],
            ]);
        }

        return view('modules.access.roles.index', $data);
    }

    public function store(RoleManagementRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $role = $this->roleManagementService->createRole($request->payload());
        } catch (RoleManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Role baru berhasil ditambahkan.',
            routeName: 'roles',
            status: 201,
            payload: [
                'role' => $this->roleManagementService->rolePayload($role),
            ],
        );
    }

    public function update(RoleManagementRequest $request, Role $role): RedirectResponse|JsonResponse
    {
        try {
            $updated = $this->roleManagementService->updateRole($role, $request->payload());
        } catch (RoleManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $updated
                ? 'Hak akses role berhasil diperbarui.'
                : 'Tidak ada perubahan pada permission role yang dipilih.',
            routeName: 'roles',
            payload: [
                'role' => $this->roleManagementService->rolePayload($role->fresh(['permissions:id,name'])),
                'changed' => $updated,
            ],
        );
    }

    public function destroy(RoleManagementRequest $request, Role $role): RedirectResponse|JsonResponse
    {
        try {
            $deleted = $this->roleManagementService->deleteRole(
                $role,
                $request->validated('reason'),
            );
        } catch (RoleManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $deleted
                ? 'Role berhasil dihapus.'
                : 'Role tidak berubah.',
            routeName: 'roles',
            payload: [
                'changed' => $deleted,
            ],
        );
    }

    private function successResponse(
        Request $request,
        string $message,
        string $routeName,
        int $status = 200,
        array $payload = [],
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $payload,
            ], $status);
        }

        return redirect()
            ->route($routeName)
            ->with('status', $message);
    }

    private function errorResponse(Request $request, RoleManagementException $exception): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => [
                    $exception->errorKey() => [$exception->getMessage()],
                ],
            ], $exception->status());
        }

        return back()
            ->withInput()
            ->withErrors([
                $exception->errorKey() => $exception->getMessage(),
            ]);
    }
}
