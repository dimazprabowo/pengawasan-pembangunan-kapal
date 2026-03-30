<div>
    {{-- Tab Navigation --}}
    <div class="flex items-center space-x-1 mb-6 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 w-fit">
        <button wire:click="setTab('compose')"
                class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $activeTab === 'compose' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span>Buat Notifikasi</span>
        </button>
        <button wire:click="setTab('history')"
                class="flex items-center space-x-2 px-4 py-2 text-sm font-medium rounded-lg transition-colors {{ $activeTab === 'history' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Riwayat Terkirim</span>
        </button>
    </div>

    @if($activeTab === 'compose')
        @include('livewire.notifications.send-notification-compose')
    @else
        <div wire:poll.5s>
            @include('livewire.notifications.send-notification-history')
        </div>
    @endif
</div>
