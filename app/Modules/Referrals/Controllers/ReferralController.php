<?php

namespace App\Modules\Referrals\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PatientReferral;
use App\Models\ReferralDestination;
use App\Models\User;
use App\Modules\Referrals\Exceptions\ReferralManagementException;
use App\Modules\Referrals\Requests\ReferralDeleteRequest;
use App\Modules\Referrals\Requests\ReferralDestinationDeleteRequest;
use App\Modules\Referrals\Requests\ReferralDestinationUpsertRequest;
use App\Modules\Referrals\Requests\ReferralIndexRequest;
use App\Modules\Referrals\Requests\ReferralIssueRequest;
use App\Modules\Referrals\Requests\ReferralPrintRequest;
use App\Modules\Referrals\Requests\ReferralReissueRequest;
use App\Modules\Referrals\Requests\ReferralUpsertRequest;
use App\Modules\Referrals\Requests\ReferralVoidRequest;
use App\Modules\Referrals\Services\ReferralService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
    ) {
    }

    public function index(ReferralIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Referrals',
            ...$this->referralService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Referral management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'destinations' => $data['destinations']->through(
                        fn (ReferralDestination $destination): array => $this->referralService->destinationPayload($destination)
                    ),
                    'referrals' => $data['referrals']->through(
                        fn (PatientReferral $referral): array => $this->referralService->referralPayload($referral)
                    ),
                ],
            ]);
        }

        return view('modules.referrals.index', $data);
    }

    public function storeDestination(ReferralDestinationUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->createDestination($request->payload(), $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Tujuan referral berhasil ditambahkan.'
                : 'Tujuan referral dengan data yang sama sudah ada.',
            routeName: 'referrals',
            status: $result['changed'] ? 201 : 200,
            payload: [
                'destination' => $this->referralService->destinationPayload($result['destination']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function updateDestination(
        ReferralDestinationUpsertRequest $request,
        ReferralDestination $destination,
    ): RedirectResponse|JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->updateDestination($destination, $request->payload(), $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Tujuan referral berhasil diperbarui.'
                : 'Tidak ada perubahan pada tujuan referral yang dipilih.',
            routeName: 'referrals',
            payload: [
                'destination' => $this->referralService->destinationPayload($result['destination']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function destroyDestination(
        ReferralDestinationDeleteRequest $request,
        ReferralDestination $destination,
    ): RedirectResponse|JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->archiveDestination($destination, $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Tujuan referral berhasil diarsipkan.'
                : 'Tujuan referral ini sudah nonaktif sebelumnya.',
            routeName: 'referrals',
            payload: [
                'destination' => $this->referralService->destinationPayload($result['destination']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function storeReferral(ReferralUpsertRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->createReferral($request->payload(), $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Draft referral berhasil dibuat.',
            routeName: 'referrals',
            status: 201,
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
                'changed' => true,
            ],
        );
    }

    public function updateReferral(ReferralUpsertRequest $request, PatientReferral $referral): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->updateReferral($referral, $request->payload(), $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Draft referral berhasil diperbarui.'
                : 'Tidak ada perubahan pada draft referral yang dipilih.',
            routeName: 'referrals',
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function issue(ReferralIssueRequest $request, PatientReferral $referral): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->issueReferral($referral, $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Referral berhasil di-issue.'
                : 'Referral ini sudah berstatus issued sebelumnya.',
            routeName: 'referrals',
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function print(ReferralPrintRequest $request, PatientReferral $referral): View|JsonResponse|RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->printReferral($referral, $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Referral print prepared successfully.',
                'data' => [
                    'referral' => $this->referralService->referralPayload($result['referral']),
                    'changed' => $result['changed'],
                ],
            ]);
        }

        return view('modules.referrals.print', [
            'title' => 'Referral Print',
            'referral' => $result['referral'],
        ]);
    }

    public function void(ReferralVoidRequest $request, PatientReferral $referral): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->voidReferral($referral, $request->payload(), $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Referral berhasil di-void.'
                : 'Referral ini sudah void dengan alasan yang sama sebelumnya.',
            routeName: 'referrals',
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function reissue(ReferralReissueRequest $request, PatientReferral $referral): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->reissueReferral($referral, $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Referral berhasil direissue dengan nomor baru.',
            routeName: 'referrals',
            status: 201,
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
                'changed' => true,
            ],
        );
    }

    public function destroyReferral(ReferralDeleteRequest $request, PatientReferral $referral): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->referralService->deleteReferral($referral, $actor);
        } catch (ReferralManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Draft referral berhasil dihapus.'
                : 'Draft referral ini sudah dihapus sebelumnya.',
            routeName: 'referrals',
            payload: [
                'referral' => $this->referralService->referralPayload($result['referral']),
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

    private function errorResponse(Request $request, ReferralManagementException $exception): RedirectResponse|JsonResponse
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
