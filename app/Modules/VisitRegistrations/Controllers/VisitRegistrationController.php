<?php

namespace App\Modules\VisitRegistrations\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VisitRegistration;
use App\Modules\VisitRegistrations\Exceptions\VisitRegistrationException;
use App\Modules\VisitRegistrations\Requests\VisitRegistrationAvailabilityRequest;
use App\Modules\VisitRegistrations\Requests\VisitRegistrationIndexRequest;
use App\Modules\VisitRegistrations\Requests\VisitRegistrationRequest;
use App\Modules\VisitRegistrations\Services\VisitRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VisitRegistrationController extends Controller
{
    public function __construct(
        private readonly VisitRegistrationService $visitRegistrationService,
    ) {
    }

    public function index(VisitRegistrationIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Visit Registrations',
            ...$this->visitRegistrationService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Visit registration data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'registrations' => $data['registrations']->through(
                        fn (VisitRegistration $registration): array => $this->visitRegistrationService->registrationPayload($registration)
                    ),
                ],
            ]);
        }

        return view('modules.visit-registrations.index', $data);
    }

    public function availability(VisitRegistrationAvailabilityRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Visit registration slot availability loaded successfully.',
            ...$this->visitRegistrationService->availability($request->payload()),
        ]);
    }

    public function store(VisitRegistrationRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $registration = $this->visitRegistrationService->createRegistration($request->payload(), $actor);
        } catch (VisitRegistrationException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: 'Registrasi kunjungan berhasil dibuat untuk ' . $registration->patient->full_name . '.',
            routeName: 'visit-registrations',
            status: 201,
            payload: [
                'registration' => $this->visitRegistrationService->registrationPayload($registration),
            ],
        );
    }

    public function update(VisitRegistrationRequest $request, VisitRegistration $registration): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $updated = $this->visitRegistrationService->updateRegistration($registration, $request->payload(), $actor);
        } catch (VisitRegistrationException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $updated
                ? 'Registrasi kunjungan berhasil diperbarui.'
                : 'Tidak ada perubahan pada registrasi kunjungan yang dipilih.',
            routeName: 'visit-registrations',
            payload: [
                'registration' => $this->visitRegistrationService->registrationPayload($registration->fresh()),
                'changed' => $updated,
            ],
        );
    }

    public function checkIn(VisitRegistrationRequest $request, VisitRegistration $registration): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $checkedIn = $this->visitRegistrationService->checkInBooking($registration, $actor);
        } catch (VisitRegistrationException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $checkedIn
                ? 'Booking berhasil check-in dan masuk antrian.'
                : 'Booking ini sudah pernah check-in sebelumnya.',
            routeName: 'visit-registrations',
            payload: [
                'registration' => $this->visitRegistrationService->registrationPayload($registration->fresh()),
                'changed' => $checkedIn,
            ],
        );
    }

    public function cancel(VisitRegistrationRequest $request, VisitRegistration $registration): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $cancelled = $this->visitRegistrationService->cancelRegistration(
                $registration,
                $actor,
                $request->validated('reason'),
            );
        } catch (VisitRegistrationException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $cancelled
                ? 'Registrasi kunjungan berhasil dibatalkan.'
                : 'Registrasi kunjungan sudah berada pada status dibatalkan.',
            routeName: 'visit-registrations',
            payload: [
                'registration' => $this->visitRegistrationService->registrationPayload($registration->fresh()),
                'changed' => $cancelled,
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

    private function errorResponse(Request $request, VisitRegistrationException $exception): RedirectResponse|JsonResponse
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
