<div>
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Notifikasi</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $unreadCount }} belum dibaca
            </p>
        </div>
        <div class="flex items-center space-x-2">
            @if($unreadCount > 0)
                <button wire:click="markAllAsRead"
                        class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Tandai Semua Dibaca
                </button>
            @endif
            <button @click="$dispatch('confirm-modal', { title: 'Hapus Notifikasi', message: 'Hapus semua notifikasi yang sudah dibaca?', action: 'deleteAllRead', type: 'danger', confirmText: 'Ya, Hapus' })"
                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Hapus Sudah Dibaca
            </button>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="flex items-center space-x-1 mb-4 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 w-fit">
        @foreach(['all' => 'Semua', 'unread' => 'Belum Dibaca', 'read' => 'Sudah Dibaca'] as $key => $label)
            <button wire:click="$set('filter', '{{ $key }}')"
                    class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                           {{ $filter === $key ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- Notification List --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($notifications as $notification)
            <div wire:key="notif-full-{{ $notification->id }}"
                 class="flex items-start gap-4 px-4 sm:px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors {{ is_null($notification->read_at) ? 'bg-blue-50/30 dark:bg-blue-900/5' : '' }}">
                {{-- Icon --}}
                <div class="flex-shrink-0 mt-0.5">
                    @switch($notification->type)
                        @case('success')
                            <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            @break
                        @case('warning')
                            <div class="w-10 h-10 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                </svg>
                            </div>
                            @break
                        @case('danger')
                            <div class="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </div>
                            @break
                        @default
                            <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                    @endswitch
                </div>

                {{-- Content --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                                {{ $notification->title }}
                                @if(is_null($notification->read_at))
                                    <span class="inline-block w-2 h-2 bg-blue-500 rounded-full ml-1"></span>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">
                                {{ $notification->message }}
                            </p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                {{ $notification->created_at->diffForHumans() }} &middot; {{ $notification->created_at->format('d M Y, H:i') }}
                            </p>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center space-x-1 flex-shrink-0">
                            @if(is_null($notification->read_at))
                                <button wire:click="markAsRead({{ $notification->id }})"
                                        title="Tandai dibaca"
                                        class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </button>
                            @endif
                            @if($notification->action_url)
                                <a href="{{ $notification->action_url }}"
                                   wire:navigate
                                   title="Buka"
                                   class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            @endif
                            <button @click="$dispatch('confirm-modal', { title: 'Hapus Notifikasi', message: 'Hapus notifikasi ini?', action: 'deleteNotification', actionParams: {{ $notification->id }}, type: 'danger', confirmText: 'Ya, Hapus' })"
                                    title="Hapus"
                                    class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Tidak ada notifikasi</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    @if($filter === 'unread')
                        Semua notifikasi sudah dibaca.
                    @elseif($filter === 'read')
                        Belum ada notifikasi yang sudah dibaca.
                    @else
                        Anda belum memiliki notifikasi.
                    @endif
                </p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($notifications->hasPages())
        <div class="mt-4">
            {{ $notifications->links() }}
        </div>
    @endif

    {{-- Confirm Modal --}}
    <x-confirm-modal />
</div>
