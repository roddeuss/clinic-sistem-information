<?php

namespace App\Modules\MedicalRecords\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MedicalRecord;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\MedicalRecords\Exceptions\MedicalRecordManagementException;
use App\Modules\MedicalRecords\Requests\MedicalRecordIndexRequest;
use App\Modules\MedicalRecords\Requests\MedicalRecordReopenRequest;
use App\Modules\MedicalRecords\Requests\MedicalRecordRequest;
use App\Modules\MedicalRecords\Services\MedicalRecordService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MedicalRecordController extends Controller
{
    public function __construct(
        private readonly MedicalRecordService $medicalRecordService,
    ) {
    }

    public function index(MedicalRecordIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Medical Records',
            ...$this->medicalRecordService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Medical record management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'visits' => $data['visits']->through(
                        fn (VisitRegistration $visit): array => $this->medicalRecordService->visitPayload($visit)
                    ),
                ],
            ]);
        }

        return view('modules.medical-records.index', $data);
    }

    public function store(MedicalRecordRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalRecordService->createMedicalRecord($request->payload(), $actor);
        } catch (MedicalRecordManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? ($result['medical_record']->status === 'final'
                    ? 'SOAP berhasil difinalkan.'
                    : 'Draft SOAP berhasil disimpan.')
                : 'Payload SOAP yang sama sudah pernah disimpan untuk visit ini.',
            routeName: 'medical-records',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'medical_record' => $this->medicalRecordService->medicalRecordPayload($result['medical_record']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function update(MedicalRecordRequest $request, MedicalRecord $medicalRecord): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalRecordService->updateMedicalRecord($medicalRecord, $request->payload(), $actor);
        } catch (MedicalRecordManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? ($result['medical_record']->status === 'final'
                    ? 'SOAP berhasil diperbarui dan difinalkan.'
                    : 'Draft SOAP berhasil diperbarui.')
                : 'Tidak ada perubahan pada SOAP yang dipilih.',
            routeName: 'medical-records',
            payload: [
                'medical_record' => $this->medicalRecordService->medicalRecordPayload($result['medical_record']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function requestReopen(MedicalRecordReopenRequest $request, MedicalRecord $medicalRecord): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalRecordService->requestReopen($medicalRecord, $request->payload()['reason'], $actor);
        } catch (MedicalRecordManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        $message = $result['mode'] === 'direct'
            ? ($result['changed']
                ? 'SOAP berhasil direopen langsung oleh admin.'
                : 'SOAP ini sudah berada pada status reopened.')
            : ($result['changed']
                ? 'Permintaan re-open SOAP berhasil dikirim.'
                : 'Permintaan re-open SOAP untuk visit ini sudah tercatat.');

        return $this->successResponse(
            $request,
            message: $message,
            routeName: 'medical-records',
            payload: [
                'medical_record' => $this->medicalRecordService->medicalRecordPayload($result['medical_record']),
                'changed' => $result['changed'],
                'mode' => $result['mode'],
            ],
        );
    }

    public function approveReopen(MedicalRecordReopenRequest $request, MedicalRecord $medicalRecord): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->medicalRecordService->approveReopen($medicalRecord, $request->payload()['reason'], $actor);
        } catch (MedicalRecordManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Permintaan re-open SOAP berhasil disetujui.'
                : 'SOAP ini sudah berada pada status reopened.',
            routeName: 'medical-records',
            payload: [
                'medical_record' => $this->medicalRecordService->medicalRecordPayload($result['medical_record']),
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

    private function errorResponse(Request $request, MedicalRecordManagementException $exception): RedirectResponse|JsonResponse
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
