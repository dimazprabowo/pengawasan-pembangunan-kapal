<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    public function mount(): void
    {
        $this->loadUnreadCount();
    }

    public function handleNewNotification(): void
    {
        $this->loadUnreadCount();
        $this->dispatch('notification-received');
    }

    public function loadUnreadCount(): void
    {
        $this->unreadCount = Notification::forUser(Auth::id())->unread()->count();
    }

    public function markAllAsRead(): void
    {
        Notification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->unreadCount = 0;
        $this->dispatch('notifications-read');
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->first();

        if ($notification) {
            $notification->markAsRead();
            $this->loadUnreadCount();
            $this->dispatch('notifications-read');
        }
    }

    public function getListeners(): array
    {
        $userId = Auth::id();

        return [
            "echo-private:user.{$userId},NewNotification" => 'handleNewNotification',
            'notification-received' => 'loadUnreadCount',
            'notifications-read' => 'loadUnreadCount',
        ];
    }

    public function render()
    {
        $notifications = Notification::forUser(Auth::id())
            ->latest()
            ->limit(10)
            ->get();

        return view('livewire.notifications.notification-bell', [
            'notifications' => $notifications,
        ]);
    }
}
