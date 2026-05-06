@props(['show' => false, 'lampiranList' => [], 'loading' => false])

@if($show)
    <div class="fixed inset-0 z-50 overflow-y-auto" x-data x-trap.noscroll="true">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20">
            {{-- Overlay --}}
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity"
                 wire:click="closeLampiranModal"></div>

            {{-- Modal Content --}}
            <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] flex flex-col z-10">
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Lampiran Harian dalam Periode</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                @if(count($lampiranList) > 0)
                                    {{ count($lampiranList) }} lampiran ditemukan
                                @else
                                    Tidak ada lampiran
                                @endif
                            </p>
                        </div>
                    </div>
                    <button wire:click="closeLampiranModal" type="button"
                        wire:loading.attr="disabled"
                        wire:target="closeLampiranModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded transition-colors disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closeLampiranModal" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closeLampiranModal" class="animate-spin w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </button>
                </div>

                {{-- Modal Body --}}
                <div class="flex-1 overflow-auto p-6">
                    @if($loading)
                        <div class="flex flex-col items-center justify-center py-12">
                            <svg class="animate-spin w-12 h-12 text-blue-600 dark:text-blue-400 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Memuat lampiran...</p>
                        </div>
                    @elseif(count($lampiranList) === 0)
                        <div class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 dark:bg-gray-700 mb-4">
                                <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Tidak Ada Lampiran</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Tidak ada lampiran dalam periode yang dipilih.
                            </p>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($lampiranList as $item)
                                <div class="bg-white dark:bg-gray-700 border-2 {{ $item['is_selected'] ? 'border-green-500 dark:border-green-400' : 'border-gray-200 dark:border-gray-600' }} rounded-lg overflow-hidden hover:shadow-md transition-all relative cursor-pointer"
                                     wire:click="toggleLampiran({{ $item['id'] }})">

                                    {{-- Checkbox Badge --}}
                                    <div class="absolute top-2 left-2 z-10 flex items-center justify-center w-6 h-6 bg-white dark:bg-gray-600 border-2 {{ $item['is_selected'] ? 'border-green-500 dark:border-green-400' : 'border-gray-300 dark:border-gray-500' }} rounded-full shadow"
                                         wire:loading.class="animate-spin"
                                         wire:target="toggleLampiran({{ $item['id'] }})">
                                        @if($item['is_selected'])
                                            <svg wire:loading.class="hidden" wire:target="toggleLampiran({{ $item['id'] }})" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @endif
                                        <svg wire:loading wire:target="toggleLampiran({{ $item['id'] }})" class="w-3 h-3 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    
                                    {{-- Preview Button - Top Right --}}
                                    @if($item['is_image'])
                                        <button type="button"
                                            wire:click.stop="previewLampiranHarian({{ $item['id'] }})"
                                            wire:loading.attr="disabled"
                                            wire:target="previewLampiranHarian({{ $item['id'] }})"
                                            class="absolute top-2 right-2 z-10 flex items-center justify-center w-8 h-8 bg-white dark:bg-gray-800 rounded-full shadow-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                                            title="Preview">
                                            <svg wire:loading.class="hidden" wire:target="previewLampiranHarian({{ $item['id'] }})" class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg wire:loading wire:target="previewLampiranHarian({{ $item['id'] }})" class="animate-spin w-4 h-4 text-gray-600 dark:text-gray-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endif
                                    
                                    {{-- Preview Image --}}
                                    <div class="aspect-video bg-gray-100 dark:bg-gray-800 relative overflow-hidden group"
                                         x-data="{ imageUrl: null, loading: false, loaded: false }"
                                         x-init="setTimeout(() => {
                                             if (!loaded) {
                                                 loading = true;
                                                 $wire.getLampiranPreview({{ $item['id'] }}).then(url => {
                                                     imageUrl = url;
                                                     loading = false;
                                                     loaded = true;
                                                 }).catch(() => {
                                                     loading = false;
                                                 });
                                             }
                                         }, 100)">
                                        @if($item['is_image'])
                                            <template x-if="loading">
                                                <div class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700">
                                                    <svg class="animate-spin w-10 h-10 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                </div>
                                            </template>
                                            <template x-if="imageUrl">
                                                <img :src="imageUrl" 
                                                     alt="{{ $item['file_name'] }}"
                                                     class="w-full h-full object-cover">
                                            </template>
                                            <template x-if="!loading && !imageUrl">
                                                <div class="w-full h-full flex items-center justify-center bg-gray-200 dark:bg-gray-700">
                                                    <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                    </svg>
                                                </div>
                                            </template>
                                        @else
                                            <div class="flex items-center justify-center h-full">
                                                <div class="text-center">
                                                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                    </svg>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-medium">{{ $item['extension'] }}</p>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- File Info --}}
                                    <div class="p-3">
                                        <div class="flex items-start gap-2 mb-2">
                                            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                            </svg>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate" title="{{ $item['file_name'] }}">
                                                    {{ $item['file_name'] }}
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ $item['file_size_formatted'] }}
                                                </p>
                                            </div>
                                        </div>

                                        {{-- Laporan Info --}}
                                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400 mb-2">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            <span>{{ $item['laporan_tanggal'] }}</span>
                                        </div>

                                        {{-- Keterangan --}}
                                        @if($item['keterangan'])
                                            <div class="mt-2 pt-2 border-t border-gray-200 dark:border-gray-600">
                                                <p class="text-xs text-gray-600 dark:text-gray-300 line-clamp-2" title="{{ $item['keterangan'] }}">
                                                    {{ $item['keterangan'] }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Modal Footer --}}
                <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <button wire:click="closeLampiranModal" type="button"
                        wire:loading.attr="disabled"
                        wire:target="closeLampiranModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-200 dark:text-gray-300 dark:hover:bg-gray-700 transition-colors disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closeLampiranModal" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closeLampiranModal" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="closeLampiranModal">Tutup</span>
                        <span wire:loading wire:target="closeLampiranModal">Menutup...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
