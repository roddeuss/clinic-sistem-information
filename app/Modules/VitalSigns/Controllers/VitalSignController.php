<?php

namespace App\Modules\VitalSigns\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VitalSignRecord;
use App\Modules\VitalSigns\Exceptions\VitalSignManagementException;
use App\Modules\VitalSigns\Requests\VitalSignIndexRequest;
use App\Modules\VitalSigns\Requests\VitalSignRequest;
use App\Modules\VitalSigns\Services\VitalSignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VitalSignController extends Controller
{
    public function __construct(
        private readonly VitalSignService $vitalSignService,
    ) {
    }

    public function index(VitalSignIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Vital Signs',
            ...$this->vitalSignService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Vital sign management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'vital_signs' => $data['vitalSigns']->through(
                        fn (VitalSignRecord $vitalSign): array => $this->vitalSignService->vitalPayload($vitalSign)
                    ),
                ],
            ]);
        }

        return view('modules.vital-signs.index', $data);
    }

    public function store(VitalSignRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->vitalSignService->createVitalSign($request->payload(), $actor);
        } catch (VitalSignManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Vital signs berhasil disimpan.'
                : 'Data vital signs yang sama sudah pernah tercatat untuk visit ini.',
            routeName: 'vital-signs',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'vital_sign' => $this->vitalSignService->vitalPayload($result['vital_sign']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function update(VitalSignRequest $request, VitalSignRecord $vitalSign): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->vitalSignService->updateVitalSign($vitalSign, $request->payload(), $actor);
        } catch (VitalSignManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Vital signs berhasil diperbarui.'
                : 'Tidak ada perubahan pada vital signs yang dipilih.',
            routeName: 'vital-signs',
            payload: [
                'vital_sign' => $this->vitalSignService->vitalPayload($result['vital_sign']),
                'changed' => $result['changed'],
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

    private function errorResponse(Request $request, VitalSignManagementException $exception): RedirectResponse|JsonResponse
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
