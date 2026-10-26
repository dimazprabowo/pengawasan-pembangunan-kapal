@props([
    'groups' => [],          // [['nama' => ..., 'rows' => [i => ['row_key', 'id', 'aktivitas', 'pic']]]]
    'fotoModel' => '',       // path wire:model foto baru, mis. "lampiran.0" / "foto"
    'fotos' => [],           // [row_key => ['file' => ..., 'is_cropped' => bool]]
    'existing' => collect(), // foto tersimpan, keyBy laporan_aktivitas_id
    'argsPrefix' => '',      // argumen awal method foto, mis. "0, " (create) / "" (edit)
    'readonly' => false,
])

{{-- Kartu lampiran digenerate dari uraian aktivitas: kategori di atas, foto, uraian di bawah (mirip halaman lampiran Word 2x2) --}}
<div x-data="{
        thumbs: {},
        pendingCrops: {},
        loadThumbs() { if ($wire.lampiranImageUrls) $wire.lampiranImageUrls().then(r => this.thumbs = r || {}) }
     }"
     x-on:lh-crop-pending.window="pendingCrops[$event.detail.rowKey] = true"
     x-on:livewire-upload-error.window="pendingCrops = {}"
     @if($existing->isNotEmpty()) x-init="loadThumbs()" x-on:lh-reload-thumbs.window="loadThumbs()" @endif>
    @unless($readonly)
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">F. Lampiran Dokumentasi</h4>
            <span class="text-xs text-gray-400">(opsional, 1 foto per aktivitas — {{ get_upload_config_display('foto_kapal') }})</span>
        </div>
    @endunless

    @php $hasCard = false; @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($groups as $group)
            @foreach($group['rows'] as $row)
                @php
                    $rowKey = $row['row_key'];
                    $existingFoto = ! empty($row['id']) ? $existing->get($row['id']) : null;
                    $newFile = $fotos[$rowKey]['file'] ?? null;
                    $newFile = is_object($newFile) ? $newFile : null;
                    // Pratinjau hasil crop (temp file kecil); file asli + cropData tetap diproses Job
                    $cropPreview = $fotos[$rowKey]['preview'] ?? null;
                    $cropPreview = ($fotos[$rowKey]['is_cropped'] ?? false) && is_object($cropPreview) ? $cropPreview : null;
                    $uraian = trim((string) ($row['aktivitas'] ?? ''));
                    $fotoError = $errors->first($fotoModel.'.'.$rowKey.'.file');
                @endphp
                {{-- Form: kartu untuk aktivitas berisi uraian / foto; readonly: hanya yang punya foto --}}
                @continue($readonly ? ! $existingFoto : ($uraian === '' && ! $existingFoto && ! $newFile))
                @php $hasCard = true; @endphp
                <div wire:key="foto-card-{{ $rowKey }}"
                    class="flex flex-col bg-white dark:bg-gray-800 rounded-lg border overflow-hidden {{ $fotoError ? 'border-red-400 dark:border-red-600' : 'border-gray-200 dark:border-gray-700' }}">
                    {{-- Kategori --}}
                    <div class="px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border-b border-gray-200 dark:border-gray-700">
                        <span class="text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $group['nama'] }}</span>
                    </div>

                    {{-- Foto --}}
                    {{-- Kotak foto tinggi fix — gambar letterbox di dalam (object-contain), card tidak ikut tinggi gambar --}}
                    <div class="h-[250px] bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center p-2">
                        @if($existingFoto && $existingFoto->hasFile() && $existingFoto->isFileCompleted() && $existingFoto->isImage())
                            {{-- Foto tersimpan: gambar asli (letterbox, rasio kotak tetap) + overlay aksi --}}
                            <div class="relative w-full h-full">
                                <div x-show="!thumbs[{{ $existingFoto->id }}]" class="absolute inset-0 flex items-center justify-center">
                                    <svg class="animate-spin w-6 h-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                                <img x-show="thumbs[{{ $existingFoto->id }}]" x-cloak
                                    :src="thumbs[{{ $existingFoto->id }}]" alt="{{ $existingFoto->file_name }}"
                                    class="w-full h-full object-contain rounded">
                                <div class="absolute top-1.5 right-1.5 flex items-center gap-0.5 bg-white/90 dark:bg-gray-800/90 rounded-lg shadow-sm px-1 py-0.5">
                                    @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                        <button type="button" wire:click="openLampiranPreview({{ $existingFoto->id }})"
                                            wire:loading.attr="disabled" wire:target="openLampiranPreview({{ $existingFoto->id }})"
                                            class="text-emerald-500 hover:text-emerald-700 p-1 disabled:opacity-50" title="Preview foto">
                                            <svg wire:loading.class="hidden" wire:target="openLampiranPreview({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg wire:loading wire:target="openLampiranPreview({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('lampiranDownload', \App\Models\LaporanHarian::class)
                                        <button type="button" wire:click="downloadLampiran({{ $existingFoto->id }})"
                                            wire:loading.attr="disabled" wire:target="downloadLampiran({{ $existingFoto->id }})"
                                            class="text-blue-500 hover:text-blue-700 p-1 disabled:opacity-50" title="Download foto">
                                            <svg wire:loading.class="hidden" wire:target="downloadLampiran({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            <svg wire:loading wire:target="downloadLampiran({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @unless($readonly)
                                        <button type="button" wire:click="confirmDeleteLampiran({{ $existingFoto->id }})"
                                            wire:loading.attr="disabled" wire:target="confirmDeleteLampiran({{ $existingFoto->id }})"
                                            class="text-red-500 hover:text-red-700 p-1 disabled:opacity-50" title="Hapus foto">
                                            <svg wire:loading.class="hidden" wire:target="confirmDeleteLampiran({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDeleteLampiran({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endunless
                                </div>
                            </div>
                        @elseif($existingFoto)
                            <div class="w-full space-y-2 text-center">
                                @if($existingFoto->isFileProcessing())
                                    <svg class="animate-spin w-8 h-8 mx-auto text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <p class="text-xs text-blue-600 dark:text-blue-400">Foto sedang diproses...</p>
                                @elseif($existingFoto->isFileFailed())
                                    <svg class="w-8 h-8 mx-auto text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-xs text-red-600 dark:text-red-400">Gagal memproses foto, hapus lalu unggah ulang.</p>
                                @else
                                    <svg class="w-8 h-8 mx-auto text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 truncate" title="{{ $existingFoto->file_name }}">{{ $existingFoto->file_name }}</p>
                                    <p class="text-[11px] text-gray-400">{{ number_format(($existingFoto->file_size ?? 0) / 1024, 0) }} KB</p>
                                @endif

                                <div class="flex items-center justify-center gap-1">
                                    @if($existingFoto->hasFile() && $existingFoto->isFileCompleted())
                                        @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                            <button type="button" wire:click="openLampiranPreview({{ $existingFoto->id }})"
                                                wire:loading.attr="disabled" wire:target="openLampiranPreview({{ $existingFoto->id }})"
                                                class="text-emerald-500 hover:text-emerald-700 p-1.5 rounded hover:bg-emerald-50 dark:hover:bg-emerald-900/20 disabled:opacity-50" title="Preview foto">
                                                <svg wire:loading.class="hidden" wire:target="openLampiranPreview({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                                <svg wire:loading wire:target="openLampiranPreview({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                        @can('lampiranDownload', \App\Models\LaporanHarian::class)
                                            <button type="button" wire:click="downloadLampiran({{ $existingFoto->id }})"
                                                wire:loading.attr="disabled" wire:target="downloadLampiran({{ $existingFoto->id }})"
                                                class="text-blue-500 hover:text-blue-700 p-1.5 rounded hover:bg-blue-50 dark:hover:bg-blue-900/20 disabled:opacity-50" title="Download foto">
                                                <svg wire:loading.class="hidden" wire:target="downloadLampiran({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                </svg>
                                                <svg wire:loading wire:target="downloadLampiran({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                </svg>
                                            </button>
                                        @endcan
                                    @endif
                                    @unless($readonly)
                                        <button type="button" wire:click="confirmDeleteLampiran({{ $existingFoto->id }})"
                                            wire:loading.attr="disabled" wire:target="confirmDeleteLampiran({{ $existingFoto->id }})"
                                            class="text-red-500 hover:text-red-700 p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50" title="Hapus foto">
                                            <svg wire:loading.class="hidden" wire:target="confirmDeleteLampiran({{ $existingFoto->id }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDeleteLampiran({{ $existingFoto->id }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endunless
                                </div>
                            </div>
                        @elseif($newFile)
                            <div class="relative w-full h-full">
                                <img src="{{ $cropPreview?->temporaryUrl() ?? $newFile->temporaryUrl() }}" alt="Pratinjau foto"
                                    x-on:load="pendingCrops[@js($rowKey)] = false"
                                    class="w-full h-full object-contain rounded">
                                <div x-show="pendingCrops[@js($rowKey)]" x-cloak
                                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-1.5 bg-gray-100/80 dark:bg-gray-900/70 rounded">
                                    <svg class="animate-spin w-6 h-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-[11px] text-gray-500 dark:text-gray-400">Memproses crop...</span>
                                </div>
                                <div class="absolute top-1.5 right-1.5 flex items-center gap-1 bg-white/90 dark:bg-gray-800/90 rounded-lg shadow-sm px-1 py-0.5">
                                    @if($fotos[$rowKey]['is_cropped'] ?? false)
                                        <span class="text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded">Cropped</span>
                                    @endif
                                    <button type="button" wire:click="openCropper({{ $argsPrefix }}'{{ $rowKey }}')"
                                        wire:loading.attr="disabled" wire:target="openCropper({{ $argsPrefix }}'{{ $rowKey }}')"
                                        class="text-blue-500 hover:text-blue-700 p-1 disabled:opacity-50" title="Crop foto">
                                        <svg wire:loading.class="hidden" wire:target="openCropper({{ $argsPrefix }}'{{ $rowKey }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3v14a1 1 0 001 1h13M3 7h14a1 1 0 011 1v13"/>
                                        </svg>
                                        <svg wire:loading wire:target="openCropper({{ $argsPrefix }}'{{ $rowKey }}')" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </button>
                                    <button type="button" wire:click="removeFoto({{ $argsPrefix }}'{{ $rowKey }}')"
                                        wire:loading.attr="disabled" wire:target="removeFoto({{ $argsPrefix }}'{{ $rowKey }}')"
                                        class="text-red-500 hover:text-red-700 p-1 disabled:opacity-50" title="Batalkan foto">
                                        <svg wire:loading.class="hidden" wire:target="removeFoto({{ $argsPrefix }}'{{ $rowKey }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                        <svg wire:loading wire:target="removeFoto({{ $argsPrefix }}'{{ $rowKey }}')" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="w-full h-full"
                                x-data="{ uploading: false, progress: 0 }"
                                x-on:livewire-upload-start="uploading = true"
                                x-on:livewire-upload-finish="uploading = false; progress = 0"
                                x-on:livewire-upload-cancel="uploading = false"
                                x-on:livewire-upload-error="uploading = false"
                                x-on:livewire-upload-progress="progress = $event.detail.progress">
                                <label class="flex flex-col items-center justify-center w-full h-full border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-white dark:bg-gray-800">
                                    <div x-show="!uploading" class="flex flex-col items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <span>Klik untuk unggah foto</span>
                                    </div>
                                    <div x-show="uploading" x-cloak class="flex items-center justify-center gap-2">
                                        <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs text-blue-600" x-text="progress + '%'"></span>
                                    </div>
                                    <input type="file" wire:model="{{ $fotoModel }}.{{ $rowKey }}.file" class="hidden"
                                        accept=".{{ implode(',.', get_allowed_mimes_array('foto_kapal')) }}">
                                </label>
                            </div>
                        @endif
                    </div>

                    {{-- Uraian aktivitas (keterangan foto) --}}
                    <div class="px-3 py-2.5 border-t border-gray-200 dark:border-gray-700">
                        @if($uraian !== '')
                            <p class="text-sm text-gray-800 dark:text-gray-200 line-clamp-3" title="{{ $uraian }}">{{ $uraian }}</p>
                        @else
                            <p class="text-sm italic text-red-500 dark:text-red-400">Uraian aktivitas belum diisi</p>
                        @endif
                        @if(! empty($row['pic']))
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">PIC: {{ $row['pic'] }}</p>
                        @endif
                        @if($fotoError)
                            <p class="text-red-500 text-xs mt-1">{{ $fotoError }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        @endforeach
    </div>

    @unless($hasCard)
        <div class="py-12 text-center bg-gray-50 dark:bg-gray-700/30 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <svg class="h-12 w-12 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            @if($readonly)
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada lampiran</p>
            @else
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Isi uraian aktivitas terlebih dahulu — kartu foto akan dibuat otomatis per aktivitas.</p>
                <button type="button" @click="section = 'aktivitas'"
                    class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Ke tab Aktivitas
                </button>
            @endif
        </div>
    @endunless
</div>
