<?php

namespace App\Livewire\Notifications;

use App\Models\Notification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationIndex extends Component
{
    use WithPagination, AuthorizesRequests;

    protected $paginationTheme = 'tailwind';

    public string $filter = 'all'; // all, unread, read

    public function mount(): void
    {
        $this->authorize('viewAny', Notification::class);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    public function markAsRead(int $notificationId): void
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->first();

        if ($notification) {
            $notification->markAsRead();
            $this->dispatch('notifications-read');
        }
    }

    public function markAllAsRead(): void
    {
        Notification::forUser(Auth::id())
            ->unread()
            ->update(['read_at' => now()]);

        $this->dispatch('notifications-read');
    }

    public function deleteNotification(int $notificationId): void
    {
        Notification::where('id', $notificationId)
            ->where('user_id', Auth::id())
            ->delete();

        $this->dispatch('notifications-read');
    }

    public function deleteAllRead(): void
    {
        Notification::forUser(Auth::id())
            ->read()
            ->delete();

        $this->dispatch('notifications-read');
    }

    public function getListeners(): array
    {
        $userId = Auth::id();

        return [
            "echo-private:user.{$userId},NewNotification" => '$refresh',
            'notifications-read' => '$refresh',
        ];
    }

    public function render()
    {
        $query = Notification::forUser(Auth::id())->latest();

        if ($this->filter === 'unread') {
            $query->unread();
        } elseif ($this->filter === 'read') {
            $query->read();
        }

        return view('livewire.notifications.notification-index', [
            'notifications' => $query->paginate(15),
            'unreadCount' => Notification::forUser(Auth::id())->unread()->count(),
        ]);
    }
}
