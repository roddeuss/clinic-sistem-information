<?php

namespace App\Modules\Queues\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Models\User;
use App\Modules\Queues\Exceptions\QueueManagementException;
use App\Modules\Queues\Requests\QueueActionRequest;
use App\Modules\Queues\Requests\QueueBoardRequest;
use App\Modules\Queues\Requests\QueueCallNextRequest;
use App\Modules\Queues\Requests\QueueIndexRequest;
use App\Modules\Queues\Services\QueueManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QueueController extends Controller
{
    public function __construct(
        private readonly QueueManagementService $queueManagementService,
    ) {
    }

    public function index(QueueIndexRequest $request): View|JsonResponse
    {
        $data = [
            'title' => 'Queues',
            ...$this->queueManagementService->getIndexData($request->filters()),
        ];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Queue management data loaded successfully.',
                'data' => [
                    'filters' => $data['filters'],
                    'queues' => $data['queueTickets']->through(
                        fn (QueueTicket $queueTicket): array => $this->queueManagementService->queuePayload($queueTicket)
                    ),
                ],
            ]);
        }

        return view('modules.queues.index', $data);
    }

    public function display(QueueBoardRequest $request): View
    {
        $queueDate = $request->queueDate();

        return view('modules.queues.display', [
            'title' => 'Queue Display',
            'queueDate' => $queueDate,
            ...$this->queueManagementService->getBoardPayload($queueDate),
        ]);
    }

    public function board(QueueBoardRequest $request): JsonResponse
    {
        $queueDate = $request->queueDate();

        return response()->json([
            'message' => 'Queue board data loaded successfully.',
            'queueDate' => $queueDate,
            ...$this->queueManagementService->getBoardPayload($queueDate),
        ]);
    }

    public function callNext(QueueCallNextRequest $request): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->queueManagementService->callNext((int) $request->validated('section_id'), $actor);
        } catch (QueueManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Antrian berikutnya berhasil dipanggil.'
                : 'Antrian ini sudah berada pada status dipanggil.',
            routeName: 'queues',
            payload: [
                'queue' => $this->queueManagementService->queuePayload($result['queue']),
                'changed' => $result['changed'],
            ],
        );
    }

    public function action(QueueActionRequest $request, QueueTicket $queue): RedirectResponse|JsonResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $result = $this->queueManagementService->applyAction($queue, $request->validated('action'), $actor);
        } catch (QueueManagementException $exception) {
            return $this->errorResponse($request, $exception);
        }

        return $this->successResponse(
            $request,
            message: $result['changed']
                ? 'Status antrian berhasil diperbarui.'
                : 'Status antrian tidak berubah karena aksi yang sama sudah pernah diterapkan.',
            routeName: 'queues',
            payload: [
                'queue' => $this->queueManagementService->queuePayload($result['queue']),
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

    private function errorResponse(Request $request, QueueManagementException $exception): RedirectResponse|JsonResponse
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
