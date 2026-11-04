<?php

namespace Tests\Feature;

use App\Livewire\Layout\Sidebar;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChatUnreadBadgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeChat(User ...$users): Chat
    {
        $chat = Chat::create(['is_group' => false, 'created_by' => $users[0]->id]);
        $chat->participants()->attach(collect($users)->pluck('id'));

        return $chat;
    }

    private function message(Chat $chat, User $sender, string $createdAt): ChatMessage
    {
        $message = ChatMessage::create([
            'chat_id' => $chat->id,
            'user_id' => $sender->id,
            'body' => 'halo',
            'type' => 'text',
        ]);
        $message->forceFill(['created_at' => $createdAt])->save();

        return $message;
    }

    private function setPivot(Chat $chat, User $user, ?string $lastRead = null): void
    {
        DB::table('chat_participants')
            ->where('chat_id', $chat->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => $lastRead]);
    }

    public function test_total_unread_counts_messages_from_others_across_chats(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $chatA = $this->makeChat($me, $other);
        $chatB = $this->makeChat($me, $other);

        $this->message($chatA, $other, '2026-01-01 10:00:00');
        $this->message($chatA, $other, '2026-01-01 10:01:00');
        $this->message($chatB, $other, '2026-01-01 10:02:00');
        // Pesan sendiri tidak dihitung
        $this->message($chatA, $me, '2026-01-01 10:03:00');

        $this->assertSame(3, Chat::totalUnreadCountForUser($me->id));
    }

    public function test_total_unread_respects_last_read_at(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $chat = $this->makeChat($me, $other);

        $this->message($chat, $other, '2026-01-01 09:00:00');
        $this->message($chat, $other, '2026-01-01 11:00:00');

        $this->setPivot($chat, $me, lastRead: '2026-01-01 10:00:00');

        $this->assertSame(1, Chat::totalUnreadCountForUser($me->id));
    }

    public function test_total_unread_zero_for_non_participant(): void
    {
        $me = User::factory()->create();
        $outsider = User::factory()->create();
        $chat = $this->makeChat($me, User::factory()->create());

        $this->message($chat, $me, '2026-01-01 10:00:00');

        $this->assertSame(0, Chat::totalUnreadCountForUser($outsider->id));
    }

    public function test_menu_items_include_chat_badge(): void
    {
        $me = User::factory()->create();
        $me->givePermissionTo(
            Permission::firstOrCreate(['name' => 'chat_view', 'guard_name' => 'web'])
        );
        $other = User::factory()->create();
        $chat = $this->makeChat($me, $other);

        $this->message($chat, $other, '2026-01-01 10:00:00');
        $this->message($chat, $other, '2026-01-01 10:01:00');

        $this->actingAs($me);

        $sidebar = new Sidebar;
        $menuItems = $sidebar->getMenuItems();
        $chatItem = collect($menuItems)->firstWhere('name', 'Chat');

        $this->assertNotNull($chatItem);
        $this->assertSame(2, $chatItem['badge'] ?? 0);
    }
}
