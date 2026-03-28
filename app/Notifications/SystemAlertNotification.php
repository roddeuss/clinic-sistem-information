<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SystemAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => $this->payload['event_key'] ?? null,
            'title' => $this->payload['title'] ?? 'System alert',
            'message' => $this->payload['message'] ?? '',
            'action_url' => $this->payload['action_url'] ?? route('dashboard'),
            'action_label' => $this->payload['action_label'] ?? 'Open',
            'module' => $this->payload['module'] ?? 'system',
            'level' => $this->payload['level'] ?? 'info',
            'meta' => $this->payload['meta'] ?? [],
        ];
    }
}
