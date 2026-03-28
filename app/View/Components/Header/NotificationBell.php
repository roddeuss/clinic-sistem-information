<?php

namespace App\View\Components\Header;

use App\Services\NotificationCenterService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class NotificationBell extends Component
{
    public function __construct(
        private readonly NotificationCenterService $notificationCenterService,
    ) {
    }

    public function render(): View|Closure|string
    {
        return view('components.header.notification-bell', $this->notificationCenterService->getHeaderData(auth()->user()));
    }
}
