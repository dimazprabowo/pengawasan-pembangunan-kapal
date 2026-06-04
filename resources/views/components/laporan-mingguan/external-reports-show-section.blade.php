@props([
    'laporanExternal' => null,
])

@if($laporanExternal && $laporanExternal->count() > 0)
<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mt-6">
    <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
            Laporan External
            <span class="ml-2 px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                {{ $laporanExternal->count() }}
            </span>
        </h3>
    </div>
    <div class="p-5">
        <div class="space-y-2">
            @foreach($laporanExternal as $external)
                <div class="flex items-center gap-3 p-2 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    @if($external->isFileProcessing())
                        <svg class="animate-spin w-5 h-5 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    @elseif($external->isFileFailed())
                        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    @endif
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ $external->judul }}</p>
                        @if($external->deskripsi)
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $external->deskripsi }}</p>
                        @endif
                        @if($external->file_name)
                            @php
                                $extension = strtoupper(pathinfo($external->file_name, PATHINFO_EXTENSION));
                            @endphp
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $external->file_name }} • {{ $extension }}
                                @if($external->file_size)
                                    • {{ number_format($external->file_size / 1024, 1) }} KB
                                @endif
                            </p>
                        @endif
                        @if($external->isFileFailed() && $external->file_error)
                            <p class="text-xs text-red-600 dark:text-red-400">{{ $external->file_error }}</p>
                        @endif
                    </div>
                    @if($external->hasFile() && $external->isFileCompleted())
                        <button wire:click="downloadExternalFile({{ $external->id }})"
                            wire:loading.attr="disabled"
                            wire:target="downloadExternalFile({{ $external->id }})"
                            class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors disabled:opacity-50">
                            <svg wire:loading.remove wire:target="downloadExternalFile({{ $external->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <svg wire:loading wire:target="downloadExternalFile({{ $external->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="downloadExternalFile({{ $external->id }})">Download</span>
                            <span wire:loading wire:target="downloadExternalFile({{ $external->id }})">Mengunduh...</span>
                        </button>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
