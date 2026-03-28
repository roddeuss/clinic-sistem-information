<?php

namespace App\Modules\Notifications\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Requests\NotificationCenterRequest;
use App\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationCenterController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenterService,
    ) {
    }

    public function index(NotificationCenterRequest $request)
    {
        return view('modules.notifications.index', [
            'title' => 'Notifications',
            ...$this->notificationCenterService->getIndexData($request->user(), $request->query()),
        ]);
    }

    public function markRead(NotificationCenterRequest $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->notificationCenterService->markAsRead($request->user(), $notification);

        return back()->with('status', 'Notifikasi ditandai sudah dibaca.');
    }

    public function markAllRead(NotificationCenterRequest $request): RedirectResponse
    {
        $this->notificationCenterService->markAllAsRead($request->user());

        return back()->with('status', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function open(NotificationCenterRequest $request, DatabaseNotification $notification): RedirectResponse
    {
        return redirect()->to(
            $this->notificationCenterService->openNotification($request->user(), $notification)
        );
    }
}
