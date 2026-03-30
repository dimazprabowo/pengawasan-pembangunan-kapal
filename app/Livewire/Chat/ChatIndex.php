<?php

namespace App\Livewire\Chat;

use App\Events\NewChatMessage;
use App\Events\UserTyping;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Throwable;

class ChatIndex extends Component
{
    use AuthorizesRequests;

    public ?int $activeChatId = null;
    public string $messageBody = '';
    public string $searchUser = '';
    public string $searchChat = '';
    public bool $showDeleteModal = false;
    public ?int $deleteChatId = null;
    public ?int $replyToId = null;
    public ?int $editingMessageId = null;
    public string $editMessageBody = '';
    public bool $showDeleteMessageModal = false;
    public ?int $deleteMessageId = null;

    /** @var array Cached chat IDs for current user — avoids repeated whereHas queries */
    private ?array $userChatIds = null;

    public function mount(?int $chat = null): void
    {
        $this->authorize('viewAny', Chat::class);

        if ($chat && $this->isUserChat($chat)) {
            $this->activeChatId = $chat;
            $this->markChatAsRead();
        }
    }

    public function selectChat(int $chatId): void
    {
        if (!$this->isUserChat($chatId)) {
            return;
        }

        $this->activeChatId = $chatId;
        $this->markChatAsRead();
        $this->messageBody = '';
        $this->replyToId = null;
    }

    public function setReply(int $messageId): void
    {
        $this->replyToId = $messageId;
    }

    public function clearReply(): void
    {
        $this->replyToId = null;
    }

    public function editMessage(int $messageId): void
    {
        $message = ChatMessage::find($messageId);

        if (!$message || $message->is_deleted) {
            return;
        }

        $this->authorize('editMessage', $message);

        $this->editingMessageId = $messageId;
        $this->editMessageBody = $message->body;
    }

    public function cancelEdit(): void
    {
        $this->editingMessageId = null;
        $this->editMessageBody = '';
    }

    public function updateMessage(): void
    {
        $body = trim($this->editMessageBody);

        if (empty($body) || !$this->editingMessageId) {
            return;
        }

        $message = ChatMessage::find($this->editingMessageId);

        if (!$message || $message->is_deleted) {
            $this->cancelEdit();
            return;
        }

        $this->authorize('editMessage', $message);

        // Only mark as edited if body actually changed
        if ($message->body !== $body) {
            $message->update([
                'body' => $body,
                'is_edited' => true,
            ]);
        }

        $this->cancelEdit();
    }

    public function confirmDeleteMessage(int $messageId): void
    {
        $this->deleteMessageId = $messageId;
        $this->showDeleteMessageModal = true;
    }

    public function deleteMessage(): void
    {
        if (!$this->deleteMessageId) {
            return;
        }

        $message = ChatMessage::find($this->deleteMessageId);

        if (!$message) {
            $this->showDeleteMessageModal = false;
            $this->deleteMessageId = null;
            return;
        }

        $this->authorize('deleteMessage', $message);

        // Soft-delete: clear body, mark as deleted (WhatsApp style)
        $message->update([
            'body' => '',
            'is_deleted' => true,
            'reply_to_id' => null,
        ]);

        $this->showDeleteMessageModal = false;
        $this->deleteMessageId = null;
    }

    public function startDirectChat(int $userId): void
    {
        $me = Auth::id();

        if ($userId === $me) {
            return;
        }

        $chat = Chat::findDirectChat($me, $userId);

        if (!$chat) {
            $chat = Chat::create([
                'is_group' => false,
                'created_by' => $me,
            ]);
            $chat->participants()->attach([$me, $userId]);
            $this->userChatIds = null; // bust cache
        }

        $this->activeChatId = $chat->id;
        $this->searchUser = '';
        $this->markChatAsRead();
    }

    public function sendMessage(): void
    {
        $body = trim($this->messageBody);

        if (empty($body) || !$this->activeChatId) {
            return;
        }

        if (!$this->isUserChat($this->activeChatId)) {
            return;
        }

        $message = ChatMessage::create([
            'chat_id' => $this->activeChatId,
            'user_id' => Auth::id(),
            'reply_to_id' => $this->replyToId,
            'body' => $body,
            'type' => 'text',
        ]);

        // Direct DB update — no need to load pivot model
        DB::table('chat_participants')
            ->where('chat_id', $this->activeChatId)
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);

        try {
            broadcast(new NewChatMessage($message->load('user')))->toOthers();
        } catch (Throwable) {
            // Broadcast failed — message is still saved in DB
        }

        $this->messageBody = '';
        $this->replyToId = null;
    }

    public function sendTyping(): void
    {
        if (!$this->activeChatId) {
            return;
        }

        try {
            broadcast(new UserTyping(
                chatId: $this->activeChatId,
                userId: Auth::id(),
                userName: Auth::user()->name,
            ))->toOthers();
        } catch (Throwable) {
            // Silently ignore — typing indicator is non-critical
        }
    }

    public function markChatAsRead(): void
    {
        if (!$this->activeChatId) {
            return;
        }

        // Direct DB update — avoids loading Chat model + pivot
        DB::table('chat_participants')
            ->where('chat_id', $this->activeChatId)
            ->where('user_id', Auth::id())
            ->update(['last_read_at' => now()]);
    }

    public function confirmDeleteChat(int $chatId): void
    {
        $this->deleteChatId = $chatId;
        $this->showDeleteModal = true;
    }

    public function deleteChat(): void
    {
        if (!$this->deleteChatId) {
            return;
        }

        if (!$this->isUserChat($this->deleteChatId)) {
            $this->showDeleteModal = false;
            $this->deleteChatId = null;
            return;
        }

        $chat = Chat::findOrFail($this->deleteChatId);
        $this->authorize('delete', $chat);

        $chat->delete();

        if ($this->activeChatId === $this->deleteChatId) {
            $this->activeChatId = null;
        }

        $this->userChatIds = null;
        $this->showDeleteModal = false;
        $this->deleteChatId = null;
    }

    public function getListeners(): array
    {
        $userId = Auth::id();

        // Use a single user-scoped channel instead of per-chat channels
        // This avoids a DB query on every Livewire request
        return [
            "echo-private:user.{$userId},NewChatMessage" => 'handleNewMessage',
            "echo-private:user.{$userId},UserTyping" => 'handleTyping',
            "echo-private:user.{$userId},NewNotification" => '$refresh',
        ];
    }

    public function handleNewMessage($event): void
    {
        if (isset($event['chat_id']) && $event['chat_id'] == $this->activeChatId) {
            $this->markChatAsRead();
        }
    }

    public function handleTyping($event): void
    {
        $this->dispatch('user-typing', userId: $event['user_id'], userName: $event['user_name']);
    }

    /**
     * Check if a chat belongs to the current user (cached per request).
     */
    private function isUserChat(int $chatId): bool
    {
        if ($this->userChatIds === null) {
            $this->userChatIds = DB::table('chat_participants')
                ->where('user_id', Auth::id())
                ->pluck('chat_id')
                ->all();
        }

        return in_array($chatId, $this->userChatIds);
    }

    public function render()
    {
        $userId = Auth::id();

        // Single query: get user's chat IDs + last_read_at from pivot table
        $participantData = DB::table('chat_participants')
            ->where('user_id', $userId)
            ->get(['chat_id', 'last_read_at']);

        $chatIds = $participantData->pluck('chat_id');
        $lastReadMap = $participantData->pluck('last_read_at', 'chat_id');

        // Single query: unread counts for all chats at once
        $unreadCounts = [];
        if ($chatIds->isNotEmpty()) {
            $unreadQuery = DB::table('chat_messages')
                ->select('chat_id', DB::raw('COUNT(*) as unread'))
                ->whereIn('chat_id', $chatIds)
                ->where('user_id', '!=', $userId);

            // Build per-chat last_read_at conditions
            $hasReadChats = $lastReadMap->filter();
            if ($hasReadChats->isNotEmpty()) {
                $unreadQuery->where(function ($q) use ($lastReadMap, $userId) {
                    foreach ($lastReadMap as $chatId => $lastRead) {
                        if ($lastRead) {
                            $q->orWhere(function ($sub) use ($chatId, $lastRead) {
                                $sub->where('chat_id', $chatId)
                                    ->where('created_at', '>', $lastRead);
                            });
                        } else {
                            $q->orWhere('chat_id', $chatId);
                        }
                    }
                });
            }

            $unreadCounts = $unreadQuery->groupBy('chat_id')
                ->pluck('unread', 'chat_id')
                ->all();
        }

        $chats = Chat::whereIn('id', $chatIds)
            ->with(['participants', 'latestMessage.user'])
            ->get()
            ->sortByDesc(fn ($chat) => $chat->latestMessage?->created_at ?? $chat->created_at);

        if ($this->searchChat) {
            $chats = $chats->filter(function ($chat) {
                return str_contains(
                    strtolower($chat->display_name),
                    strtolower($this->searchChat)
                );
            });
        }

        $activeChat = null;
        $messages = collect();

        if ($this->activeChatId) {
            // Reuse already-loaded chats instead of a second query
            $activeChat = $chats->firstWhere('id', $this->activeChatId);

            if ($activeChat) {
                $messages = ChatMessage::where('chat_id', $this->activeChatId)
                    ->with(['user', 'replyTo.user'])
                    ->orderBy('created_at', 'asc')
                    ->limit(100)
                    ->get();
            }
        }

        $searchResults = collect();
        if (strlen($this->searchUser) >= 2) {
            $searchResults = User::active()
                ->where('id', '!=', $userId)
                ->where(function ($q) {
                    $q->where('name', 'ilike', "%{$this->searchUser}%")
                      ->orWhere('email', 'ilike', "%{$this->searchUser}%");
                })
                ->limit(10)
                ->get();
        }

        return view('livewire.chat.chat-index', [
            'chats' => $chats,
            'activeChat' => $activeChat,
            'messages' => $messages,
            'searchResults' => $searchResults,
            'unreadCounts' => $unreadCounts,
        ]);
    }
}
