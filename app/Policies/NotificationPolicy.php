<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    /**
     * Determine whether the user can view their own notifications.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('notifications_view');
    }

    /**
     * Determine whether the user can view a specific notification.
     * Only the owner can view their own notification.
     */
    public function view(User $user, Notification $notification): bool
    {
        return $user->can('notifications_view') && $user->id === $notification->user_id;
    }

    /**
     * Determine whether the user can send notifications to others.
     */
    public function send(User $user): bool
    {
        return $user->can('notifications_send');
    }

    /**
     * Determine whether the user can delete a notification.
     * Only the owner can delete their own notification.
     */
    public function delete(User $user, Notification $notification): bool
    {
        return $user->can('notifications_view') && $user->id === $notification->user_id;
    }
}
