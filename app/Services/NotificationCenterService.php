<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemAlertNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

class NotificationCenterService
{
    public function getHeaderData(?User $user, int $limit = 6): array
    {
        if (! $user) {
            return [
                'unreadCount' => 0,
                'recentNotifications' => collect(),
            ];
        }

        return [
            'unreadCount' => $user->unreadNotifications()->count(),
            'recentNotifications' => $user->notifications()
                ->latest()
                ->limit($limit)
                ->get(),
        ];
    }

    public function getIndexData(User $user, array $filters): array
    {
        $filters = [
            'status' => (string) ($filters['status'] ?? ''),
            'search' => trim((string) ($filters['search'] ?? '')),
        ];

        return [
            'filters' => $filters,
            'unreadCount' => $user->unreadNotifications()->count(),
            'notifications' => $user->notifications()
                ->when($filters['status'] === 'unread', fn (Builder $query) => $query->whereNull('read_at'))
                ->when($filters['status'] === 'read', fn (Builder $query) => $query->whereNotNull('read_at'))
                ->when($filters['search'] !== '', fn (Builder $query) => $query->whereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', ['%' . mb_strtolower($filters['search']) . '%']))
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ];
    }

    public function markAsRead(User $user, DatabaseNotification $notification): void
    {
        $owned = $this->ownedNotification($user, $notification);

        if ($owned->read_at === null) {
            $owned->markAsRead();
        }
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications->markAsRead();
    }

    public function openNotification(User $user, DatabaseNotification $notification): string
    {
        $owned = $this->ownedNotification($user, $notification);

        if ($owned->read_at === null) {
            $owned->markAsRead();
        }

        return (string) data_get($owned->data, 'action_url', route('notifications'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function notifyUsers(iterable $users, array $payload): void
    {
        collect($users)
            ->filter(fn ($user): bool => $user instanceof User && $user->is_active)
            ->unique(fn (User $user): int => $user->id)
            ->each(fn (User $user) => $user->notify(new SystemAlertNotification($payload)));
    }

    /**
     * @param  list<string>  $roles
     * @param  array<string, mixed>  $payload
     */
    public function notifyRoles(array $roles, array $payload): void
    {
        if ($roles === []) {
            return;
        }

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $roles))
            ->get();

        $this->notifyUsers($users, $payload);
    }

    private function ownedNotification(User $user, DatabaseNotification $notification): DatabaseNotification
    {
        return $user->notifications()
            ->whereKey($notification->getKey())
            ->firstOrFail();
    }
}
