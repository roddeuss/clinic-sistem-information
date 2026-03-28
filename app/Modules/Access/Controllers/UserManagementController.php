<?php

namespace App\Modules\Access\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Access\Exceptions\UserManagementException;
use App\Modules\Access\Requests\UserManagementIndexRequest;
use App\Modules\Access\Requests\UserManagementRequest;
use App\Modules\Access\Services\UserManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function __construct(
        private readonly UserManagementService $userManagementService,
    ) {
    }

    public function index(UserManagementIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'User Management',
            ...$this->userManagementService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'users' => $data['users']->through(fn (User $user): array => $this->userManagementService->userPayload($user)),
                ],
            ]);
        }

        return view('modules.access.users.index', $data);
    }

    public function store(UserManagementRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $user = $this->userManagementService->createUser($request->payload(), $actor);
        } catch (UserManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'User dashboard berhasil ditambahkan.',
            routeName: 'users',
            status: 201,
            payload: [
                'user' => $this->userManagementService->userPayload($user),
            ],
        );
    }

    public function update(UserManagementRequest $request, User $user): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $updated = $this->userManagementService->updateUser($user, $request->payload(), $actor);
        } catch (UserManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $updated
                ? 'Akses user berhasil diperbarui.'
                : 'Tidak ada perubahan pada user yang dipilih.',
            routeName: 'users',
            payload: [
                'user' => $this->userManagementService->userPayload($user->fresh(['employee', 'roles:id,name'])),
                'changed' => $updated,
            ],
        );
    }

    public function archive(UserManagementRequest $request, User $user): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $archived = $this->userManagementService->archiveUser(
                $user,
                $actor,
                $request->validated('reason'),
            );
        } catch (UserManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $archived
                ? 'User berhasil dinonaktifkan.'
                : 'User sudah berada pada status nonaktif.',
            routeName: 'users',
            payload: [
                'user' => $this->userManagementService->userPayload($user->fresh(['employee', 'roles:id,name'])),
                'changed' => $archived,
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

    private function errorResponse(Request $request, UserManagementException $exception): RedirectResponse|JsonResponse
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
