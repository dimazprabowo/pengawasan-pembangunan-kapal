<div class="flex h-[calc(100vh-10rem)] bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden"
     wire:poll.visible.10s
     x-data="{
         typingUser: null,
         typingTimeout: null,
         showMobileList: true,
     }"
     @user-typing.window="
         typingUser = $event.detail.userName;
         clearTimeout(typingTimeout);
         typingTimeout = setTimeout(() => typingUser = null, 3000);
     ">

    {{-- Left Panel: Chat List --}}
    <div class="w-full md:w-80 lg:w-96 border-r border-gray-200 dark:border-gray-700 flex flex-col flex-shrink-0"
         :class="{ 'hidden md:flex': !showMobileList && {{ $activeChatId ? 'true' : 'false' }} }">

        {{-- Search Header --}}
        <div class="p-4 border-b border-gray-200 dark:border-gray-700 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Chat</h2>
                {{-- New Chat Toggle --}}
                <div x-data="{ showNew: false }" class="relative">
                    <button @click="showNew = !showNew"
                            class="p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                            title="Chat baru">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                    </button>

                    {{-- New Chat Dropdown --}}
                    <div x-show="showNew" @click.outside="showNew = false"
                         x-transition
                         class="absolute right-0 mt-2 w-72 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-50 p-3"
                         style="display: none;">
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-2 block">Cari user untuk chat</label>
                        <input type="text"
                               wire:model.live.debounce.300ms="searchUser"
                               placeholder="Ketik nama atau email..."
                               class="w-full text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">

                        @if($searchResults->count() > 0)
                            <div class="mt-2 max-h-48 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach($searchResults as $user)
                                    <button wire:click="startDirectChat({{ $user->id }})"
                                            @click="showNew = false; showMobileList = false"
                                            class="w-full flex items-center space-x-3 px-2 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg transition-colors text-left">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-xs font-semibold flex-shrink-0">
                                            {{ substr($user->name, 0, 1) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $user->name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</p>
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        @elseif(strlen($searchUser) >= 2)
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400 text-center py-2">Tidak ditemukan</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Search Existing Chats --}}
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="searchChat"
                       placeholder="Cari percakapan..."
                       class="w-full pl-10 pr-4 py-2 text-sm rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>

        {{-- Chat List --}}
        <div class="flex-1 overflow-y-auto">
            @forelse($chats as $chat)
                @php
                    $unread = $unreadCounts[$chat->id] ?? 0;
                @endphp
                <button wire:key="chat-{{ $chat->id }}"
                        wire:click="selectChat({{ $chat->id }})"
                        @click="showMobileList = false"
                        class="w-full flex items-center space-x-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-left border-b border-gray-100 dark:border-gray-700/50
                               {{ $activeChatId === $chat->id ? 'bg-blue-50 dark:bg-blue-900/20 border-l-2 border-l-blue-500' : '' }}">
                    {{-- Avatar --}}
                    <div class="relative flex-shrink-0">
                        <div class="w-11 h-11 rounded-full bg-gradient-to-br {{ $chat->is_group ? 'from-purple-500 to-purple-600' : 'from-blue-500 to-blue-600' }} flex items-center justify-center text-white font-semibold text-sm">
                            @if($chat->is_group)
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                </svg>
                            @else
                                {{ substr($chat->display_name, 0, 1) }}
                            @endif
                        </div>
                    </div>

                    {{-- Chat Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                                {{ $chat->display_name }}
                            </p>
                            @if($chat->latestMessage)
                                <span class="text-xs text-gray-400 dark:text-gray-500 flex-shrink-0 ml-2">
                                    {{ $chat->latestMessage->created_at->shortRelativeDiffForHumans() }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                @if($chat->latestMessage)
                                    @if($chat->latestMessage->is_deleted)
                                        <span class="italic">Pesan telah dihapus</span>
                                    @else
                                        @if($chat->latestMessage->user_id === auth()->id())
                                            <span class="text-gray-400">Anda: </span>
                                        @endif
                                        {{ Str::limit($chat->latestMessage->body, 40) }}
                                    @endif
                                @else
                                    <span class="italic">Belum ada pesan</span>
                                @endif
                            </p>
                            @if($unread > 0)
                                <span class="inline-flex items-center justify-center w-5 h-5 text-[10px] font-bold text-white bg-blue-500 rounded-full flex-shrink-0 ml-2">
                                    {{ $unread > 9 ? '9+' : $unread }}
                                </span>
                            @endif
                        </div>
                    </div>
                </button>
            @empty
                <div class="px-4 py-12 text-center">
                    <svg class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada percakapan</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Mulai chat baru dengan tombol di atas</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Right Panel: Conversation --}}
    <div class="flex-1 flex flex-col min-w-0"
         :class="{ 'hidden md:flex': showMobileList }">

        @if($activeChat)
            {{-- Chat Header --}}
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                <div class="flex items-center space-x-3 min-w-0">
                    {{-- Back button (mobile) --}}
                    <button @click="showMobileList = true"
                            class="md:hidden p-1.5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <div class="w-9 h-9 rounded-full bg-gradient-to-br {{ $activeChat->is_group ? 'from-purple-500 to-purple-600' : 'from-blue-500 to-blue-600' }} flex items-center justify-center text-white font-semibold text-sm flex-shrink-0">
                        @if($activeChat->is_group)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        @else
                            {{ substr($activeChat->display_name, 0, 1) }}
                        @endif
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $activeChat->display_name }}</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-show="typingUser" x-cloak>
                            <span class="text-blue-500 dark:text-blue-400" x-text="typingUser + ' sedang mengetik...'"></span>
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400" x-show="!typingUser">
                            {{ $activeChat->is_group ? $activeChat->participants->count() . ' peserta' : '' }}
                        </p>
                    </div>
                </div>

                @can('chat_delete')
                    <button wire:click="confirmDeleteChat({{ $activeChat->id }})"
                            class="p-2 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                            title="Hapus chat">
                        <svg class="w-4 h-4" wire:loading.remove wire:target="confirmDeleteChat({{ $activeChat->id }})" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <svg wire:loading wire:target="confirmDeleteChat({{ $activeChat->id }})" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                @endcan
            </div>

            {{-- Messages --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3"
                 id="chat-messages"
                 x-ref="chatMessages"
                 x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)">

                @php $lastDate = null; @endphp
                @foreach($messages as $message)
                    @php
                        $messageDate = $message->created_at->format('Y-m-d');
                        $isMe = $message->user_id === auth()->id();
                    @endphp

                    {{-- Date Separator --}}
                    @if($lastDate !== $messageDate)
                        <div class="flex items-center justify-center my-4">
                            <span class="px-3 py-1 text-xs font-medium text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 rounded-full">
                                @if($message->created_at->isToday())
                                    Hari Ini
                                @elseif($message->created_at->isYesterday())
                                    Kemarin
                                @else
                                    {{ $message->created_at->format('d M Y') }}
                                @endif
                            </span>
                        </div>
                        @php $lastDate = $messageDate; @endphp
                    @endif

                    {{-- Message Bubble --}}
                    <div wire:key="msg-{{ $message->id }}" class="group flex items-end gap-1 {{ $isMe ? 'justify-end' : 'justify-start' }}">

                        {{-- Action buttons (left side for own messages) --}}
                        @if($isMe && !$message->is_deleted)
                            <div x-data="{ showActions: false }" class="relative opacity-0 group-hover:opacity-100 transition-opacity mb-2 flex items-center gap-0.5">
                                <button wire:click="setReply({{ $message->id }})"
                                        class="p-1 text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 rounded transition-colors"
                                        title="Balas">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                    </svg>
                                </button>
                                <button @click="showActions = !showActions"
                                        class="p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded transition-colors"
                                        title="Lainnya">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 6a2 2 0 110-4 2 2 0 010 4zM10 12a2 2 0 110-4 2 2 0 010 4zM10 18a2 2 0 110-4 2 2 0 010 4z"/>
                                    </svg>
                                </button>
                                <div x-show="showActions" @click.outside="showActions = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     x-transition:leave="transition ease-in duration-75"
                                     x-transition:leave-start="opacity-100 scale-100"
                                     x-transition:leave-end="opacity-0 scale-95"
                                     class="absolute right-0 bottom-full mb-1 w-32 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-20 py-1"
                                     style="display: none;">
                                    <button wire:click="editMessage({{ $message->id }})"
                                            @click="showActions = false"
                                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        Edit
                                    </button>
                                    <button wire:click="confirmDeleteMessage({{ $message->id }})"
                                            @click="showActions = false"
                                            class="w-full flex items-center gap-2 px-3 py-1.5 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                        Hapus
                                    </button>
                                </div>
                            </div>
                        @endif

                        <div class="max-w-[75%] sm:max-w-[65%]">
                            @if(!$isMe && $activeChat->is_group)
                                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1 ml-1">
                                    {{ $message->user->name }}
                                </p>
                            @endif

                            @if($message->is_deleted)
                                {{-- Deleted message (WhatsApp style) --}}
                                <div class="px-4 py-2.5 rounded-2xl {{ $isMe
                                    ? 'bg-blue-600/50 rounded-br-md'
                                    : 'bg-gray-100 dark:bg-gray-700 rounded-bl-md' }}">
                                    <p class="text-sm italic {{ $isMe ? 'text-blue-200' : 'text-gray-400 dark:text-gray-500' }} flex items-center gap-1.5">
                                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                        </svg>
                                        {{ $isMe ? 'Anda menghapus pesan ini' : 'Pesan telah dihapus' }}
                                    </p>
                                    <p class="text-[10px] mt-1 {{ $isMe ? 'text-blue-300/60' : 'text-gray-400 dark:text-gray-500' }} text-right">
                                        {{ $message->created_at->format('H:i') }}
                                    </p>
                                </div>
                            @elseif($editingMessageId === $message->id)
                                {{-- Inline edit form --}}
                                <div class="px-4 py-2.5 rounded-2xl bg-blue-600 rounded-br-md">
                                    <div class="flex items-center gap-1.5 mb-2">
                                        <svg class="w-3 h-3 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                        </svg>
                                        <span class="text-[11px] font-medium text-blue-200">Edit pesan</span>
                                    </div>
                                    <textarea wire:model="editMessageBody"
                                              @keydown.escape="$wire.cancelEdit()"
                                              @keydown.ctrl.enter.prevent="$wire.updateMessage()"
                                              rows="2"
                                              class="w-full text-sm rounded-lg border-blue-400/30 bg-blue-700/50 text-white placeholder-blue-300/50 focus:ring-blue-300 focus:border-blue-300 resize-none"
                                              style="min-height: 36px; max-height: 100px;"></textarea>
                                    <div class="flex items-center justify-between mt-2">
                                        <div class="flex items-center gap-1.5">
                                            <button wire:click="cancelEdit"
                                                    class="px-2.5 py-1 text-[11px] font-medium text-blue-200 hover:text-white bg-blue-700/50 hover:bg-blue-700 rounded-md transition-colors">
                                                Batal
                                            </button>
                                            <button wire:click="updateMessage"
                                                    class="px-2.5 py-1 text-[11px] font-medium text-blue-600 bg-white hover:bg-blue-50 rounded-md transition-colors">
                                                Simpan
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                {{-- Normal message --}}
                                <div class="px-4 py-2.5 rounded-2xl {{ $isMe
                                    ? 'bg-blue-600 text-white rounded-br-md'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded-bl-md' }}">
                                    {{-- Reply reference --}}
                                    @if($message->replyTo)
                                        <div class="mb-2 px-3 py-1.5 rounded-lg border-l-2 {{ $isMe
                                            ? 'bg-blue-700/50 border-blue-300'
                                            : 'bg-gray-200 dark:bg-gray-600 border-gray-400 dark:border-gray-500' }}">
                                            @if($message->replyTo->is_deleted)
                                                <p class="text-[11px] italic {{ $isMe ? 'text-blue-200/70' : 'text-gray-400 dark:text-gray-500' }}">
                                                    Pesan telah dihapus
                                                </p>
                                            @else
                                                <p class="text-[11px] font-semibold {{ $isMe ? 'text-blue-200' : 'text-gray-600 dark:text-gray-300' }}">
                                                    {{ $message->replyTo->user->name ?? 'Dihapus' }}
                                                </p>
                                                <p class="text-[11px] truncate {{ $isMe ? 'text-blue-100/80' : 'text-gray-500 dark:text-gray-400' }}">
                                                    {{ Str::limit($message->replyTo->body, 60) }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                    <p class="text-sm whitespace-pre-wrap break-words">{{ $message->body }}</p>
                                    <div class="flex items-center justify-end gap-1.5 mt-1">
                                        @if($message->is_edited)
                                            <span class="text-[10px] italic {{ $isMe ? 'text-blue-200/70' : 'text-gray-400 dark:text-gray-500' }}">diedit</span>
                                        @endif
                                        <span class="text-[10px] {{ $isMe ? 'text-blue-200' : 'text-gray-400 dark:text-gray-500' }}">
                                            {{ $message->created_at->format('H:i') }}
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Reply button (right side for other's messages) --}}
                        @if(!$isMe && !$message->is_deleted)
                            <button wire:click="setReply({{ $message->id }})"
                                    class="opacity-0 group-hover:opacity-100 p-1 text-gray-400 hover:text-blue-500 dark:hover:text-blue-400 rounded transition-opacity mb-2"
                                    title="Balas">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                </svg>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Typing Indicator --}}
            <div x-show="typingUser" x-cloak class="px-4 pb-1">
                <div class="flex items-center space-x-2 text-xs text-gray-500 dark:text-gray-400">
                    <div class="flex space-x-1">
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                    </div>
                    <span x-text="typingUser + ' sedang mengetik...'"></span>
                </div>
            </div>

            {{-- Reply Preview Bar --}}
            @if($replyToId)
                @php
                    $replyMessage = $messages->firstWhere('id', $replyToId);
                @endphp
                @if($replyMessage)
                    <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-2 bg-gray-50 dark:bg-gray-800/50 flex items-center gap-3">
                        <div class="flex-1 min-w-0 border-l-2 border-blue-500 pl-3">
                            <p class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                                {{ $replyMessage->user->name }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                {{ Str::limit($replyMessage->body, 80) }}
                            </p>
                        </div>
                        <button wire:click="clearReply"
                                class="flex-shrink-0 p-1 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                @endif
            @endif

            {{-- Message Input --}}
            <div class="border-t border-gray-200 dark:border-gray-700 p-3 sm:p-4"
                 x-data="{
                     sending: false,
                     resize() {
                         const el = this.$refs.msgInput;
                         if (!el) return;
                         el.style.height = 'auto';
                         el.style.height = Math.min(el.scrollHeight, 120) + 'px';
                     },
                     async send() {
                         if (this.sending) return;
                         const body = ($wire.messageBody || '').trim();
                         if (!body) return;
                         this.sending = true;
                         try {
                             await $wire.sendMessage();
                         } finally {
                             this.sending = false;
                             this.$nextTick(() => this.resize());
                         }
                     }
                 }"
                 x-init="$watch('$wire.messageBody', () => $nextTick(() => resize()))">
                <form @submit.prevent="send()" class="flex items-end gap-2">
                    <textarea x-ref="msgInput"
                              wire:model.live.debounce.150ms="messageBody"
                              @keydown.ctrl.enter.prevent="send()"
                              @input.throttle.500ms="$wire.sendTyping()"
                              @input="resize()"
                              placeholder="Ketik pesan... (Ctrl+Enter untuk kirim)"
                              rows="1"
                              class="flex-1 text-sm rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500 resize-none py-2.5 px-4"
                              style="min-height: 40px; max-height: 120px; overflow-y: auto;"></textarea>
                    <button type="submit"
                            class="flex-shrink-0 w-10 h-10 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-colors disabled:opacity-50"
                            :disabled="sending || !($wire.messageBody || '').trim()">
                        {{-- Send icon --}}
                        <svg x-show="!sending" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        {{-- Loading spinner --}}
                        <svg x-show="sending" x-cloak class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </form>
            </div>
        @else
            {{-- No Chat Selected --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center px-6">
                    <div class="w-20 h-20 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <svg class="w-10 h-10 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">Pilih Percakapan</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Pilih percakapan dari daftar atau mulai chat baru</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Delete Chat Confirmation Modal --}}
    <x-delete-confirmation-modal wire:model="showDeleteModal" confirmAction="deleteChat">
        <x-slot name="title">Hapus Percakapan</x-slot>
        Apakah Anda yakin ingin menghapus percakapan ini? Semua pesan akan hilang dan tidak dapat dikembalikan.
        <x-slot name="confirmText">Ya, Hapus</x-slot>
        <x-slot name="cancelText">Batal</x-slot>
    </x-delete-confirmation-modal>

    {{-- Delete Message Confirmation Modal --}}
    <x-delete-confirmation-modal wire:model="showDeleteMessageModal" confirmAction="deleteMessage">
        <x-slot name="title">Hapus Pesan</x-slot>
        Pesan ini akan dihapus untuk semua orang. Pesan yang sudah dihapus tidak dapat dikembalikan.
        <x-slot name="confirmText">Ya, Hapus</x-slot>
        <x-slot name="cancelText">Batal</x-slot>
    </x-delete-confirmation-modal>
</div>

@script
<script>
    // Auto-scroll chat messages to bottom after every Livewire update
    Livewire.hook('morph.updated', ({ el }) => {
        if (el.id === 'chat-messages') {
            requestAnimationFrame(() => {
                el.scrollTop = el.scrollHeight;
            });
        }
    });

    // Preserve textarea height after Livewire morph
    Livewire.hook('morph.updated', ({ el }) => {
        if (el.tagName === 'TEXTAREA' && el.hasAttribute('x-ref')) {
            requestAnimationFrame(() => {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 120) + 'px';
            });
        }
    });

    // Also scroll on initial load
    const chatEl = document.getElementById('chat-messages');
    if (chatEl) {
        chatEl.scrollTop = chatEl.scrollHeight;
    }
</script>
@endscript
