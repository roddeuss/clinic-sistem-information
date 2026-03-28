<?php

namespace App\Modules\Prescriptions\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\PrescriptionItem;
use App\Models\User;
use App\Modules\Prescriptions\Exceptions\PrescriptionManagementException;
use App\Modules\Prescriptions\Requests\PrescriptionCloseRemainingRequest;
use App\Modules\Prescriptions\Requests\PrescriptionDispenseRequest;
use App\Modules\Prescriptions\Requests\PrescriptionFinalizeRequest;
use App\Modules\Prescriptions\Requests\PrescriptionIndexRequest;
use App\Modules\Prescriptions\Requests\PrescriptionItemDeleteRequest;
use App\Modules\Prescriptions\Requests\PrescriptionItemUpsertRequest;
use App\Modules\Prescriptions\Requests\PrescriptionOverrideRequest;
use App\Modules\Prescriptions\Requests\PrescriptionUpsertRequest;
use App\Modules\Prescriptions\Services\PrescriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class PrescriptionController extends Controller
{
    public function __construct(
        private readonly PrescriptionService $prescriptionService,
    ) {
    }

    public function index(PrescriptionIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Prescription',
            ...$this->prescriptionService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Prescription management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'visits' => $data['visits']->through(
                        fn ($visit): array => $this->prescriptionService->visitPayload($visit)
                    ),
                    'dispensing_items' => $data['dispensingItems']->through(
                        fn ($item): array => $this->prescriptionService->prescriptionItemPayload($item)
                    ),
                ],
            ]);
        }

        return view('modules.prescriptions.index', $data);
    }

    public function store(PrescriptionUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->createPrescription($request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Prescription berhasil dibuat.'
                : 'Prescription dengan data yang sama sudah ada untuk visit ini.',
            routeName: 'prescriptions',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'prescription' => $this->prescriptionService->prescriptionPayload($result['prescription']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function update(PrescriptionUpsertRequest $request, Prescription $prescription): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->updatePrescription($prescription, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Prescription berhasil diperbarui.'
                : 'Tidak ada perubahan pada prescription yang dipilih.',
            routeName: 'prescriptions',
            payload: [
                'prescription' => $this->prescriptionService->prescriptionPayload($result['prescription']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function finalize(PrescriptionFinalizeRequest $request, Prescription $prescription): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->finalizePrescription($prescription, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Prescription berhasil difinalkan.'
                : 'Prescription ini sudah final sebelumnya.',
            routeName: 'prescriptions',
            payload: [
                'prescription' => $this->prescriptionService->prescriptionPayload($result['prescription']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function storeItem(PrescriptionItemUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->createPrescriptionItem($request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Item prescription berhasil ditambahkan.'
                : 'Item prescription dengan data yang sama sudah ada.',
            routeName: 'prescriptions',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'item' => $this->prescriptionService->prescriptionItemPayload($result['item']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function updateItem(PrescriptionItemUpsertRequest $request, PrescriptionItem $item): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->updatePrescriptionItem($item, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Item prescription berhasil diperbarui.'
                : 'Tidak ada perubahan pada item prescription yang dipilih.',
            routeName: 'prescriptions',
            payload: [
                'item' => $this->prescriptionService->prescriptionItemPayload($result['item']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyItem(PrescriptionItemDeleteRequest $request, PrescriptionItem $item): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->cancelPrescriptionItem($item, $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Item prescription berhasil dibatalkan.'
                : 'Item prescription ini sudah berada pada status cancelled.',
            routeName: 'prescriptions',
            payload: [
                'item' => $this->prescriptionService->prescriptionItemPayload($result['item']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function dispense(PrescriptionDispenseRequest $request, PrescriptionItem $item): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $dispense = $this->prescriptionService->dispenseItem($item, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Dispensing berhasil disimpan.',
            routeName: 'prescriptions',
            status: 201,
            payload: [
                'dispense' => $this->prescriptionService->dispensePayload($dispense),
            ],
        );
    }

    public function closeRemaining(PrescriptionCloseRemainingRequest $request, PrescriptionItem $item): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->closeRemainingItem($item, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Sisa fulfillment item prescription berhasil ditutup.'
                : 'Sisa fulfillment item prescription ini sudah ditutup sebelumnya.',
            routeName: 'prescriptions',
            payload: [
                'item' => $this->prescriptionService->prescriptionItemPayload($result['item']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function overrideInteractions(PrescriptionOverrideRequest $request, PrescriptionItem $item): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->prescriptionService->overrideMajorInteractions($item, $request->payload(), $actor);
        } catch (PrescriptionManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Major drug interaction berhasil dioverride dengan alasan.'
                : 'Override interaksi untuk item ini sudah sesuai dan tidak berubah.',
            routeName: 'prescriptions',
            payload: [
                'item' => $this->prescriptionService->prescriptionItemPayload($result['item']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function printLabel(Request $request, PrescriptionDispense $dispense): View
    {
        $user = $request->user();

        abort_unless(
            $user?->hasRole('super-admin') || ($user?->can('view prescription management') ?? false),
            403
        );

        return view('modules.prescriptions.label', [
            'title' => 'Dispense Label',
            ...$this->prescriptionService->getLabelData($dispense),
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

    private function errorResponse(Request $request, PrescriptionManagementException $exception): RedirectResponse|JsonResponse
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
