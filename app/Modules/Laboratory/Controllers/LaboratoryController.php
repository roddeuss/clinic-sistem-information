<?php

namespace App\Modules\Laboratory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryTest;
use App\Models\User;
use App\Modules\Laboratory\Exceptions\LaboratoryManagementException;
use App\Modules\Laboratory\Requests\LaboratoryIndexRequest;
use App\Modules\Laboratory\Requests\LaboratoryOrderDeleteRequest;
use App\Modules\Laboratory\Requests\LaboratoryOrderPrintRequest;
use App\Modules\Laboratory\Requests\LaboratoryOrderUpsertRequest;
use App\Modules\Laboratory\Requests\LaboratoryTestDeleteRequest;
use App\Modules\Laboratory\Requests\LaboratoryTestUpsertRequest;
use App\Modules\Laboratory\Services\LaboratoryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LaboratoryController extends Controller
{
    public function __construct(
        private readonly LaboratoryService $laboratoryService,
    ) {
    }

    public function index(LaboratoryIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Diagnostics',
            ...$this->laboratoryService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Diagnostics management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'tests' => $data['tests']->through(
                        fn (LaboratoryTest $test): array => $this->laboratoryService->testPayload($test)
                    ),
                    'orders' => $data['orders']->through(
                        fn (LaboratoryOrder $order): array => $this->laboratoryService->orderPayload($order)
                    ),
                ],
            ]);
        }

        return view('modules.laboratory.index', $data);
    }

    public function storeTest(LaboratoryTestUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->createTest($request->payload(), $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Diagnostic test berhasil ditambahkan.'
                : 'Diagnostic test dengan data yang sama sudah ada.',
            routeName: 'laboratory',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'test' => $this->laboratoryService->testPayload($result['test']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function updateTest(LaboratoryTestUpsertRequest $request, LaboratoryTest $test): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->updateTest($test, $request->payload(), $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Diagnostic test berhasil diperbarui.'
                : 'Tidak ada perubahan pada diagnostic test yang dipilih.',
            routeName: 'laboratory',
            payload: [
                'test' => $this->laboratoryService->testPayload($result['test']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyTest(LaboratoryTestDeleteRequest $request, LaboratoryTest $test): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->archiveTest($test, $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Diagnostic test berhasil diarsipkan.'
                : 'Diagnostic test ini sudah nonaktif sebelumnya.',
            routeName: 'laboratory',
            payload: [
                'test' => $this->laboratoryService->testPayload($result['test']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function storeOrder(LaboratoryOrderUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->createOrder($request->payload(), $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Diagnostic order berhasil ditambahkan.',
            routeName: 'laboratory',
            status: 201,
            payload: [
                'order' => $this->laboratoryService->orderPayload($result['order']),
                'changed' => true,
            ],
        );
    }

    public function updateOrder(LaboratoryOrderUpsertRequest $request, LaboratoryOrder $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->updateOrder($order, $request->payload(), $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Diagnostic order berhasil diperbarui.'
                : 'Tidak ada perubahan pada diagnostic order yang dipilih.',
            routeName: 'laboratory',
            payload: [
                'order' => $this->laboratoryService->orderPayload($result['order']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyOrder(LaboratoryOrderDeleteRequest $request, LaboratoryOrder $order): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->cancelOrder($order, $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Diagnostic order berhasil dibatalkan.'
                : 'Diagnostic order ini sudah berada pada status cancelled.',
            routeName: 'laboratory',
            payload: [
                'order' => $this->laboratoryService->orderPayload($result['order']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function requestPrint(LaboratoryOrderPrintRequest $request, LaboratoryOrder $order): View|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->printRequest($order, $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Diagnostic request print prepared successfully.',
                'data' => [
                    'order' => $this->laboratoryService->orderPayload($result['order']),
                    'changed' => $result['changed'],
                ],
            ]);
        }

        return view('modules.laboratory.request-print', [
            'title' => 'Diagnostic Request Print',
            'order' => $result['order'],
        ]);
    }

    public function resultPrint(LaboratoryOrderPrintRequest $request, LaboratoryOrder $order): View|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->laboratoryService->printResult($order, $actor);
        } catch (LaboratoryManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Diagnostic result print prepared successfully.',
                'data' => [
                    'order' => $this->laboratoryService->orderPayload($result['order']),
                    'changed' => $result['changed'],
                ],
            ]);
        }

        return view('modules.laboratory.result-print', [
            'title' => 'Diagnostic Result Print',
            'order' => $result['order'],
        ]);
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

    private function errorResponse(Request $request, LaboratoryManagementException $exception): RedirectResponse|JsonResponse
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
