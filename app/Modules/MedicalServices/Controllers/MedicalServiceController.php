<?php

namespace App\Modules\MedicalServices\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MedicalService;
use App\Models\User;
use App\Models\VisitMedicalService;
use App\Modules\MedicalServices\Exceptions\MedicalServiceManagementException;
use App\Modules\MedicalServices\Requests\MedicalServiceDeleteRequest;
use App\Modules\MedicalServices\Requests\MedicalServiceIndexRequest;
use App\Modules\MedicalServices\Requests\MedicalServiceMasterUpsertRequest;
use App\Modules\MedicalServices\Requests\MedicalServiceOrderDeleteRequest;
use App\Modules\MedicalServices\Requests\MedicalServiceOrderUpsertRequest;
use App\Modules\MedicalServices\Services\MedicalServiceModuleService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MedicalServiceController extends Controller
{
    public function __construct(
        private readonly MedicalServiceModuleService $medicalServiceModuleService,
    ) {
    }

    public function index(MedicalServiceIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Medical Services',
            ...$this->medicalServiceModuleService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Medical service management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'masters' => $data['masters']->through(
                        fn (MedicalService $medicalService): array => $this->medicalServiceModuleService->masterPayload($medicalService)
                    ),
                    'orders' => $data['orders']->through(
                        fn (VisitMedicalService $order): array => $this->medicalServiceModuleService->orderPayload($order)
                    ),
                ],
            ]);
        }

        return view('modules.medical-services.index', $data);
    }

    public function store(MedicalServiceMasterUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->createMaster($request->payload(), $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Medical service berhasil ditambahkan.'
                : 'Medical service dengan data yang sama sudah ada.',
            routeName: 'medical-services',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'medical_service' => $this->medicalServiceModuleService->masterPayload($result['medicalService']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function update(MedicalServiceMasterUpsertRequest $request, MedicalService $medicalService): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->updateMaster($medicalService, $request->payload(), $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Medical service berhasil diperbarui.'
                : 'Tidak ada perubahan pada medical service yang dipilih.',
            routeName: 'medical-services',
            payload: [
                'medical_service' => $this->medicalServiceModuleService->masterPayload($result['medicalService']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroy(MedicalServiceDeleteRequest $request, MedicalService $medicalService): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->archiveMaster($medicalService, $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Medical service berhasil diarsipkan.'
                : 'Medical service ini sudah nonaktif sebelumnya.',
            routeName: 'medical-services',
            payload: [
                'medical_service' => $this->medicalServiceModuleService->masterPayload($result['medicalService']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function storeOrder(MedicalServiceOrderUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->createOrder($request->payload(), $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Service order berhasil ditambahkan.',
            routeName: 'medical-services',
            status: 201,
            payload: [
                'order' => $this->medicalServiceModuleService->orderPayload($result['order']),
                'changed' => true,
            ],
        );
    }

    public function updateOrder(MedicalServiceOrderUpsertRequest $request, VisitMedicalService $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->updateOrder($order, $request->payload(), $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Service order berhasil diperbarui.'
                : 'Tidak ada perubahan pada service order yang dipilih.',
            routeName: 'medical-services',
            payload: [
                'order' => $this->medicalServiceModuleService->orderPayload($result['order']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyOrder(MedicalServiceOrderDeleteRequest $request, VisitMedicalService $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalServiceModuleService->cancelOrder($order, $actor);
        } catch (MedicalServiceManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Service order berhasil dibatalkan.'
                : 'Service order ini sudah berada pada status cancelled.',
            routeName: 'medical-services',
            payload: [
                'order' => $this->medicalServiceModuleService->orderPayload($result['order']),
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

    private function errorResponse(Request $request, MedicalServiceManagementException $exception): RedirectResponse|JsonResponse
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
