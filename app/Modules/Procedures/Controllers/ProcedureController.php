<?php

namespace App\Modules\Procedures\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ProcedureMaster;
use App\Models\User;
use App\Models\VisitProcedure;
use App\Modules\Procedures\Exceptions\ProcedureManagementException;
use App\Modules\Procedures\Requests\ProcedureIndexRequest;
use App\Modules\Procedures\Requests\ProcedureMasterDeleteRequest;
use App\Modules\Procedures\Requests\ProcedureMasterUpsertRequest;
use App\Modules\Procedures\Requests\ProcedureOrderDeleteRequest;
use App\Modules\Procedures\Requests\ProcedureOrderUpsertRequest;
use App\Modules\Procedures\Services\ProcedureService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $procedureService,
    ) {
    }

    public function index(ProcedureIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Procedures',
            ...$this->procedureService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Procedure management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'masters' => $data['masters']->through(
                        fn (ProcedureMaster $master): array => $this->procedureService->masterPayload($master)
                    ),
                    'orders' => $data['orders']->through(
                        fn (VisitProcedure $order): array => $this->procedureService->orderPayload($order)
                    ),
                ],
            ]);
        }

        return view('modules.procedures.index', $data);
    }

    public function storeMaster(ProcedureMasterUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->createMaster($request->payload(), $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Procedure master berhasil ditambahkan.'
                : 'Procedure master dengan data yang sama sudah ada.',
            routeName: 'procedures',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'master' => $this->procedureService->masterPayload($result['master']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function updateMaster(ProcedureMasterUpsertRequest $request, ProcedureMaster $master): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->updateMaster($master, $request->payload(), $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Procedure master berhasil diperbarui.'
                : 'Tidak ada perubahan pada procedure master yang dipilih.',
            routeName: 'procedures',
            payload: [
                'master' => $this->procedureService->masterPayload($result['master']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyMaster(ProcedureMasterDeleteRequest $request, ProcedureMaster $master): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->archiveMaster($master, $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Procedure master berhasil diarsipkan.'
                : 'Procedure master ini sudah nonaktif sebelumnya.',
            routeName: 'procedures',
            payload: [
                'master' => $this->procedureService->masterPayload($result['master']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function storeOrder(ProcedureOrderUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->createOrder($request->payload(), $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Procedure order berhasil ditambahkan.',
            routeName: 'procedures',
            status: 201,
            payload: [
                'order' => $this->procedureService->orderPayload($result['order']),
                'changed' => true,
            ],
        );
    }

    public function updateOrder(ProcedureOrderUpsertRequest $request, VisitProcedure $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->updateOrder($order, $request->payload(), $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Procedure order berhasil diperbarui.'
                : 'Tidak ada perubahan pada procedure order yang dipilih.',
            routeName: 'procedures',
            payload: [
                'order' => $this->procedureService->orderPayload($result['order']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyOrder(ProcedureOrderDeleteRequest $request, VisitProcedure $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->procedureService->cancelOrder($order, $actor);
        } catch (ProcedureManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Procedure order berhasil dibatalkan.'
                : 'Procedure order ini sudah berada pada status cancelled.',
            routeName: 'procedures',
            payload: [
                'order' => $this->procedureService->orderPayload($result['order']),
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

    private function errorResponse(Request $request, ProcedureManagementException $exception): RedirectResponse|JsonResponse
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
