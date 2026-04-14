@props(['laporan', 'queueStatus'])

@php
    $fileStatus = $laporan->file_status ?? null;
    $isProcessing = in_array($fileStatus, ['pending', 'processing']);
    $isCompleted = $fileStatus === 'completed';
    $isFailed = $fileStatus === 'failed';
    $hasFile = $laporan->hasFile();
@endphp

{{-- File Status Display --}}
@if($isProcessing)
    {{-- File is being processed --}}
    <div class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg"
         wire:poll.3s>
        {{-- Loading Icon --}}
        <svg class="animate-spin w-5 h-5 text-blue-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-blue-700 dark:text-blue-300">
                {{ $fileStatus === 'pending' ? 'File dalam antrian...' : 'File sedang diproses...' }}
            </p>
            
            {{-- Queue Status Warning --}}
            @if(!$queueStatus['active'])
                <div class="flex items-start gap-1.5 mt-1">
                    <svg class="w-3.5 h-3.5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-xs text-amber-600 dark:text-amber-400">
                        {{ $queueStatus['message'] }}
                    </p>
                </div>
            @endif
        </div>
    </div>

@elseif($isFailed)
    {{-- File processing failed --}}
    <div class="flex items-center gap-3 px-3 py-2.5 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div class="flex-1 min-w-0">
            <p class="text-sm font-medium text-red-700 dark:text-red-300">Gagal memproses file</p>
            @if($laporan->file_error)
                <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">{{ $laporan->file_error }}</p>
            @endif
        </div>
    </div>

@elseif($hasFile && $isCompleted)
    {{-- File successfully processed --}}
    <div class="flex items-center gap-3 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <span class="text-sm text-emerald-700 dark:text-emerald-300 truncate flex-1">{{ $laporan->file_name }}</span>
        <span class="text-xs text-emerald-500 dark:text-emerald-400">{{ number_format($laporan->file_size / 1024, 0) }} KB</span>
        <button wire:click="previewFile"
            wire:loading.attr="disabled"
            wire:target="previewFile"
            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-0.5 rounded transition-colors" title="Preview file">
            <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </button>
        @can('laporan_download')
            <button wire:click="downloadFile"
                wire:loading.attr="disabled"
                wire:target="downloadFile"
                class="text-blue-500 hover:text-blue-700 p-0.5 rounded transition-colors" title="Download file">
                <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </button>
        @endcan
    </div>
@endif
