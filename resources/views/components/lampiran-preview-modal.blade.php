@props(['show' => false, 'lampiran', 'laporan'])

@if($show && $lampiran)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-trap.noscroll="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"
                 wire:click="closePreviewModal"></div>

            {{-- Modal Content --}}
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col z-10">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 min-w-0">
                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $lampiran->file_name }}</h3>
                    </div>
                    <div class="flex items-center gap-2">
                        @can('laporan_lampiran_download')
                            <a href="{{ route('laporan.lampiran.download', [$laporan, $lampiran]) }}"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download
                            </a>
                        @endcan
                        <button wire:click="closePreviewModal" type="button"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-auto p-1" style="min-height: 500px;">
                    @php
                        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
                        $isPdf = $extension === 'pdf';
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    @endphp

                    @if($isPdf)
                        <iframe src="{{ route('laporan.lampiran.preview', [$laporan, $lampiran]) }}" class="w-full h-full rounded" style="min-height: 500px;"></iframe>
                    @elseif($isImage)
                        <div class="flex items-center justify-center h-full p-4">
                            <img src="{{ route('laporan.lampiran.preview', [$laporan, $lampiran]) }}" 
                                 alt="{{ $lampiran->file_name }}" 
                                 class="max-w-full max-h-full object-contain rounded">
                        </div>
                    @else
                        {{-- For other file types --}}
                        <div class="flex flex-col items-center justify-center h-full py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ $lampiran->file_name }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">
                                Ukuran: {{ number_format($lampiran->file_size / 1024, 0) }} KB
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                Preview langsung tidak tersedia untuk file {{ strtoupper($extension) }}.
                            </p>
                            @can('laporan_lampiran_download')
                                <a href="{{ route('laporan.lampiran.download', [$laporan, $lampiran]) }}"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Download untuk Membuka
                                </a>
                            @endcan
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
