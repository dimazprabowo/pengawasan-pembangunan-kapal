@props(['show' => false, 'lampiran', 'laporan', 'imageUrl' => null])

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
                        <div class="flex flex-col min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ $lampiran->file_name }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Ukuran: {{ number_format($lampiran->file_size / 1024, 0) }} KB
                            </p>
                        </div>
                    </div>
                    <button wire:click="closePreviewModal" type="button"
                        wire:loading.attr="disabled"
                        wire:target="closePreviewModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded transition-colors disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closePreviewModal" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closePreviewModal" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-auto p-1" style="min-height: 500px;">
                    @php
                        $extension = strtolower(pathinfo($lampiran->file_name, PATHINFO_EXTENSION));
                        $isPdf = $extension === 'pdf';
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    @endphp

                    @if($isPdf)
                        <div class="flex flex-col items-center justify-center h-full py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ $lampiran->file_name }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                Klik tombol di bawah untuk membuka file PDF.
                            </p>
                            <button wire:click="previewLampiran({{ $lampiran->id }})" type="button"
                                wire:loading.attr="disabled"
                                wire:target="previewLampiran({{ $lampiran->id }})"
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors disabled:opacity-50">
                                <svg wire:loading.class="hidden" wire:target="previewLampiran({{ $lampiran->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg wire:loading wire:target="previewLampiran({{ $lampiran->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span wire:loading.remove wire:target="previewLampiran({{ $lampiran->id }})">Buka PDF</span>
                                <span wire:loading wire:target="previewLampiran({{ $lampiran->id }})">Membuka...</span>
                            </button>
                        </div>
                    @elseif($isImage)
                        <div class="flex items-center justify-center h-full p-4">
                            @if($imageUrl)
                                <img src="{{ $imageUrl }}"
                                     alt="{{ $lampiran->file_name }}"
                                     class="max-w-full max-h-full object-contain rounded">
                            @else
                                <p class="text-sm text-red-500 dark:text-red-400">Gagal memuat gambar</p>
                            @endif
                        </div>
                    @else
                        {{-- For other file types --}}
                        <div class="flex flex-col items-center justify-center h-full py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">{{ $lampiran->file_name }}</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                                Preview langsung tidak tersedia untuk file {{ strtoupper($extension) }}.
                            </p>
                            @can('laporan_lampiran_download')
                                <button wire:click="downloadLampiran({{ $lampiran->id }})" type="button"
                                    wire:loading.attr="disabled"
                                    wire:target="downloadLampiran({{ $lampiran->id }})"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors disabled:opacity-50">
                                    <svg wire:loading.class="hidden" wire:target="downloadLampiran({{ $lampiran->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    <svg wire:loading wire:target="downloadLampiran({{ $lampiran->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span wire:loading.remove wire:target="downloadLampiran({{ $lampiran->id }})">Download untuk Membuka</span>
                                    <span wire:loading wire:target="downloadLampiran({{ $lampiran->id }})">Mengunduh...</span>
                                </button>
                            @endcan
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-200 dark:border-gray-700">
                    <button wire:click="closePreviewModal" type="button"
                        wire:loading.attr="disabled"
                        wire:target="closePreviewModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closePreviewModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closePreviewModal" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="closePreviewModal">Batal</span>
                        <span wire:loading wire:target="closePreviewModal">Menutup...</span>
                    </button>
                    @can('laporan_lampiran_download')
                        <button wire:click="downloadLampiran({{ $lampiran->id }})" type="button"
                            wire:loading.attr="disabled"
                            wire:target="downloadLampiran({{ $lampiran->id }})"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 hover:bg-blue-700 text-white transition-colors disabled:opacity-50">
                            <svg wire:loading.class="hidden" wire:target="downloadLampiran({{ $lampiran->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <svg wire:loading wire:target="downloadLampiran({{ $lampiran->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span wire:loading.remove wire:target="downloadLampiran({{ $lampiran->id }})">Download</span>
                            <span wire:loading wire:target="downloadLampiran({{ $lampiran->id }})">Mengunduh...</span>
                        </button>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endif
