<?php

namespace App\Modules\DoctorLetters\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DoctorLetter;
use App\Models\User;
use App\Modules\DoctorLetters\Exceptions\DoctorLetterManagementException;
use App\Modules\DoctorLetters\Requests\DoctorLetterDeleteRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterIndexRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterIssueRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterPrintRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterReissueRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterUpsertRequest;
use App\Modules\DoctorLetters\Requests\DoctorLetterVoidRequest;
use App\Modules\DoctorLetters\Services\DoctorLetterService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DoctorLetterController extends Controller
{
    public function __construct(
        private readonly DoctorLetterService $doctorLetterService,
    ) {
    }

    public function index(DoctorLetterIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Doctor Letters',
            ...$this->doctorLetterService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Doctor letter management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'letters' => $data['letters']->through(
                        fn (DoctorLetter $doctorLetter): array => $this->doctorLetterService->letterPayload($doctorLetter)
                    ),
                ],
            ]);
        }

        return view('modules.doctor-letters.index', $data);
    }

    public function store(DoctorLetterUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->createLetter($request->payload(), $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Draft surat dokter berhasil dibuat.'
                : 'Draft surat dokter dengan data yang sama sudah ada.',
            routeName: 'doctor-letters',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function update(DoctorLetterUpsertRequest $request, DoctorLetter $doctorLetter): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->updateLetter($doctorLetter, $request->payload(), $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Draft surat dokter berhasil diperbarui.'
                : 'Tidak ada perubahan pada draft surat dokter yang dipilih.',
            routeName: 'doctor-letters',
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function issue(DoctorLetterIssueRequest $request, DoctorLetter $doctorLetter): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->issueLetter($doctorLetter, $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Surat dokter berhasil di-issue.'
                : 'Surat dokter ini sudah berstatus issued sebelumnya.',
            routeName: 'doctor-letters',
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function print(DoctorLetterPrintRequest $request, DoctorLetter $doctorLetter): View|JsonResponse|RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->printLetter($doctorLetter, $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Doctor letter print prepared successfully.',
                'data' => [
                    'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                    'changed' => $result['changed'],
                ],
            ]);
        }

        return view('modules.doctor-letters.print', [
            'title' => 'Doctor Letter Print',
            'doctorLetter' => $result['doctorLetter'],
        ]);
    }

    public function void(DoctorLetterVoidRequest $request, DoctorLetter $doctorLetter): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->voidLetter($doctorLetter, $request->payload(), $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Surat dokter berhasil di-void.'
                : 'Surat dokter ini sudah void dengan alasan yang sama sebelumnya.',
            routeName: 'doctor-letters',
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function reissue(DoctorLetterReissueRequest $request, DoctorLetter $doctorLetter): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->reissueLetter($doctorLetter, $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Surat dokter berhasil direissue dengan nomor baru.',
            routeName: 'doctor-letters',
            status: 201,
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
                'changed' => true,
            ],
        );
    }

    public function destroy(DoctorLetterDeleteRequest $request, DoctorLetter $doctorLetter): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->doctorLetterService->deleteLetter($doctorLetter, $actor);
        } catch (DoctorLetterManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Draft surat dokter berhasil dihapus.'
                : 'Draft surat dokter ini sudah dihapus sebelumnya.',
            routeName: 'doctor-letters',
            payload: [
                'doctor_letter' => $this->doctorLetterService->letterPayload($result['doctorLetter']),
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

    private function errorResponse(Request $request, DoctorLetterManagementException $exception): RedirectResponse|JsonResponse
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
