<?php

namespace App\Modules\Patients\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\User;
use App\Modules\Patients\Exceptions\PatientManagementException;
use App\Modules\Patients\Requests\PatientManagementIndexRequest;
use App\Modules\Patients\Requests\PatientRequest;
use App\Modules\Patients\Services\PatientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {
    }

    public function index(PatientManagementIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Patients',
            ...$this->patientService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Patient management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'patients' => $data['patients']->through(
                        fn (Patient $patient): array => $this->patientService->patientPayload($patient)
                    ),
                ],
            ]);
        }

        return view('modules.patients.index', $data);
    }

    public function store(PatientRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->patientService->createPatient($request->payload(), $actor);
        } catch (PatientManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Patient berhasil ditambahkan.',
            routeName: 'patients',
            status: 201,
            payload: [
                'patient' => $this->patientService->patientPayload($result['patient']),
                'duplicate_warnings' => $result['duplicate_warnings'],
            ],
            duplicateWarnings: $result['duplicate_warnings'],
        );
    }

    public function update(PatientRequest $request, Patient $patient): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->patientService->updatePatient($patient, $request->payload(), $actor);
        } catch (PatientManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Patient berhasil diperbarui.'
                : 'Tidak ada perubahan pada patient yang dipilih.',
            routeName: 'patients',
            payload: [
                'patient' => $this->patientService->patientPayload($result['patient']),
                'duplicate_warnings' => $result['duplicate_warnings'],
                'changed' => $result['changed'],
            ],
            duplicateWarnings: $result['duplicate_warnings'],
        );
    }

    public function archive(PatientRequest $request, Patient $patient): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $archived = $this->patientService->archivePatient(
                $patient,
                $actor,
                $request->validated('reason'),
            );
        } catch (PatientManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $archived
                ? 'Patient berhasil dinonaktifkan.'
                : 'Patient sudah berada pada status nonaktif.',
            routeName: 'patients',
            payload: [
                'patient' => $this->patientService->patientPayload($patient->fresh(['branchRecords.branch'])),
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
        array $duplicateWarnings = [],
    ): RedirectResponse|JsonResponse {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'data' => $payload,
            ], $status);
        }

        return redirect()
            ->route($routeName)
            ->with('status', $message)
            ->with('duplicate_warnings', $duplicateWarnings);
    }

    private function errorResponse(Request $request, PatientManagementException $exception): RedirectResponse|JsonResponse
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
