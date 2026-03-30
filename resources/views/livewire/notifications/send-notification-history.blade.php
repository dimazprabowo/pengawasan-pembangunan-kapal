@php
    $typeConfig = [
        'info'    => ['bg' => 'bg-blue-100 dark:bg-blue-900/30',    'text' => 'text-blue-600 dark:text-blue-400',    'label' => 'Info',       'icon' => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
        'success' => ['bg' => 'bg-green-100 dark:bg-green-900/30',  'text' => 'text-green-600 dark:text-green-400',  'label' => 'Sukses',     'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
        'warning' => ['bg' => 'bg-yellow-100 dark:bg-yellow-900/30','text' => 'text-yellow-600 dark:text-yellow-400','label' => 'Peringatan', 'icon' => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z'],
        'danger'  => ['bg' => 'bg-red-100 dark:bg-red-900/30',      'text' => 'text-red-600 dark:text-red-400',      'label' => 'Bahaya',     'icon' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

<div class="space-y-4">
    {{-- Stats Row --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        @foreach($historyStats as $stat)
            @php $cfg = $typeConfig[$stat['type']] ?? $typeConfig['info']; @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-full {{ $cfg['bg'] }} flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 {{ $cfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cfg['icon'] }}"/>
                    </svg>
                </div>
                <div>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $stat['count'] }}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $cfg['label'] }}</p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Search & Filter --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
        <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input type="text"
                   wire:model.live.debounce.300ms="historySearch"
                   placeholder="Cari judul atau pesan notifikasi..."
                   class="w-full pl-9 pr-4 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
        </div>
    </div>

    {{-- History List --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($history as $item)
            @php $cfg = $typeConfig[$item->type] ?? $typeConfig['info']; @endphp
            <div wire:key="hist-{{ $item->id }}" class="flex items-start gap-4 px-5 py-4">
                <div class="flex-shrink-0 mt-0.5">
                    <div class="w-10 h-10 rounded-full {{ $cfg['bg'] }} flex items-center justify-center">
                        <svg class="w-5 h-5 {{ $cfg['text'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $cfg['icon'] }}"/>
                        </svg>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $item->title }}</p>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $cfg['bg'] }} {{ $cfg['text'] }}">
                                    {{ $cfg['label'] }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ $item->message }}</p>
                            <div class="flex items-center gap-3 mt-1.5 flex-wrap">
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    {{ $item->created_at->diffForHumans() }} &middot; {{ $item->created_at->format('d M Y, H:i') }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-500">
                                    Penerima: <span class="font-medium text-gray-600 dark:text-gray-300">{{ $item->user->name ?? '-' }}</span>
                                </span>
                                @if($item->action_url)
                                    <a href="{{ $item->action_url }}" target="_blank"
                                       class="text-xs text-blue-500 hover:text-blue-700 dark:hover:text-blue-300 transition-colors truncate max-w-[200px]">
                                        {{ $item->action_url }}
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="flex-shrink-0">
                            @if($item->read_at)
                                <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Dibaca
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500">
                                    <span class="w-2 h-2 bg-blue-400 rounded-full"></span>
                                    Belum dibaca
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="px-6 py-16 text-center">
                <svg class="w-14 h-14 text-gray-300 dark:text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                </svg>
                <h3 class="text-base font-medium text-gray-900 dark:text-white mb-1">Belum ada notifikasi terkirim</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">Notifikasi yang Anda kirim akan muncul di sini.</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($history->hasPages())
        <div>{{ $history->links() }}</div>
    @endif
</div>
