<?php

namespace App\Services;

use App\Events\NewNotification;
use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public static function send(
        int $userId,
        string $title,
        string $message,
        string $type = 'info',
        ?string $icon = null,
        ?string $actionUrl = null,
        ?array $data = null,
    ): Notification {
        $notification = Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'icon' => $icon,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);

        try {
            broadcast(new NewNotification($notification));
        } catch (\Throwable $e) {
            logger()->warning('Broadcast failed, notification saved to DB only.', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    public static function sendToMany(
        array $userIds,
        string $title,
        string $message,
        string $type = 'info',
        ?string $icon = null,
        ?string $actionUrl = null,
        ?array $data = null,
    ): void {
        foreach ($userIds as $userId) {
            static::send($userId, $title, $message, $type, $icon, $actionUrl, $data);
        }
    }

    public static function sendToAll(
        string $title,
        string $message,
        string $type = 'info',
        ?string $icon = null,
        ?string $actionUrl = null,
        ?array $data = null,
    ): void {
        $userIds = User::active()->pluck('id')->toArray();
        static::sendToMany($userIds, $title, $message, $type, $icon, $actionUrl, $data);
    }
}
