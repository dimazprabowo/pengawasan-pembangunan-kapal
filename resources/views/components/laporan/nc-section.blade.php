@props([
    'rows' => [],            // [['row_key','id','kendala','tindakan','pic']]
    'wirePrefix' => '',      // 'items.0.nc' (create) / 'nc' (edit)
    'argsPrefix' => '',      // '0, ' (create) / '' (edit)
    'addMethod' => 'addNc',  // 'addNc(0)' (create) / 'addNc' (edit)
    'existing' => collect(), // LaporanNc tersimpan, keyBy id (edit/show)
    'fotoModel' => '',       // path upload foto baru: 'ncFoto.0' (create) / 'ncFoto' (edit); kosong = tanpa upload
    'fotos' => [],           // [row_key => ['file' => ..., 'cropData' => ..., 'is_cropped' => bool]]
    'readonly' => false,
    'title' => 'Non-Conformity (NC)',
])

{{-- Section NC: kartu per NC — kendala (wajib), tindakan, PIC + 1 foto bukti temuan.
     Form Temuan/Penutupan (scan TTD) dikelola di List NC. --}}
@php
    // Thumbs lazy hanya bila ada foto NC tersimpan — create tidak punya method ncFotoImageUrls
    $hasNcFiles = $existing->contains(fn ($nc) => $nc->foto !== null);
@endphp
<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700"
    x-data="{
        thumbs: {},
        pendingCrops: {},
        loadThumbs() { if ($wire.ncFotoImageUrls) $wire.ncFotoImageUrls().then(r => this.thumbs = r || {}) }
    }"
    x-on:lh-crop-pending.window="pendingCrops[$event.detail.rowKey] = true"
    x-on:livewire-upload-error.window="pendingCrops = {}"
    @if($hasNcFiles) x-init="loadThumbs()" x-on:lh-reload-thumbs.window="loadThumbs()" @endif>
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2 min-w-0">
            <span class="truncate">{{ $title }}</span>
            @if(count($rows) > 0)
                <span class="min-w-[20px] px-1.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-[10px] leading-4 text-center text-amber-700 dark:text-amber-300">{{ count($rows) }}</span>
            @endif
        </h4>
        @unless($readonly)
            <button type="button" wire:click="{{ $addMethod }}"
                wire:loading.attr="disabled" wire:target="{{ $addMethod }}"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50">
                <svg wire:loading.class="hidden" wire:target="{{ $addMethod }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="{{ $addMethod }}" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                Tambah NC
            </button>
        @endunless
    </div>

    <div class="space-y-3">
    @forelse($rows as $i => $row)
        @php
            $rowKey = $row['row_key'] ?? $i;
            $nc = ! empty($row['id']) ? $existing->get($row['id']) : null;
            $isClosed = $nc?->isClosed() ?? false;
            $savedFoto = $nc?->foto;
            $newFile = $fotos[$rowKey]['file'] ?? null;
            $newFile = is_object($newFile) ? $newFile : null;
            $cropPreview = $fotos[$rowKey]['preview'] ?? null;
            $cropPreview = ($fotos[$rowKey]['is_cropped'] ?? false) && is_object($cropPreview) ? $cropPreview : null;
            $fotoError = $fotoModel !== '' ? $errors->first($fotoModel.'.'.$rowKey.'.file') : null;
        @endphp
        <div wire:key="nc-row-{{ $rowKey }}-{{ $i }}"
            class="rounded-xl border shadow-sm {{ $isClosed ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-900/10' : 'border-amber-200 dark:border-amber-800/50 bg-white dark:bg-gray-800' }} overflow-hidden">
            <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b {{ $isClosed ? 'border-emerald-100 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-900/20' : 'border-amber-100 dark:border-amber-800/40 bg-amber-50/60 dark:bg-amber-900/10' }}">
                <div class="flex items-center gap-2 min-w-0">
                    @if($nc?->nomor)
                        <span class="text-xs font-bold tracking-wide px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300">{{ $nc->nomor }}</span>
                    @else
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">NC #{{ $i + 1 }}</span>
                    @endif
                    @if($nc)
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $nc->status->badgeClass() }}">{{ $nc->status->label() }}</span>
                    @endif
                </div>
                @unless($readonly || $isClosed)
                    <button type="button" wire:click="confirmRemoveNc({{ $argsPrefix }}{{ $i }})"
                        wire:loading.attr="disabled" wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})"
                        class="text-red-500 hover:text-red-700 p-1 disabled:opacity-50" title="Hapus NC">
                        <svg wire:loading.class="hidden" wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <svg wire:loading wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </button>
                @endunless
            </div>

            @if($readonly)
                <div class="px-4 py-3 space-y-1.5">
                    <p class="text-sm text-gray-900 dark:text-white">{{ $row['kendala'] ?? '-' }}</p>
                    @if(!empty($row['tindakan']))
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tindakan: {{ $row['tindakan'] }}</p>
                    @endif
                    @if(!empty($row['pic']))
                        <p class="text-xs text-gray-500 dark:text-gray-400">PIC: {{ $row['pic'] }}</p>
                    @endif
                </div>
            @else
            <fieldset {{ $isClosed ? 'disabled' : '' }} class="px-4 py-3 space-y-2 disabled:opacity-80">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Kendala / Temuan <span class="text-red-500">*</span></label>
                    <textarea wire:model="{{ $wirePrefix }}.{{ $i }}.kendala" rows="2"
                        placeholder="Uraikan ketidaksesuaian yang ditemukan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800"></textarea>
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.kendala')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tindakan</label>
                    <textarea wire:model="{{ $wirePrefix }}.{{ $i }}.tindakan" rows="2"
                        placeholder="Rencana / tindakan perbaikan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800"></textarea>
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.tindakan')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">PIC</label>
                    <input type="text" wire:model="{{ $wirePrefix }}.{{ $i }}.pic"
                        placeholder="Penanggung jawab perbaikan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800">
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.pic')" class="mt-1" />
                </div>
            </fieldset>
            @endif

            {{-- Foto bukti temuan — 1 foto per NC; foto baru menggantikan yang lama saat disimpan --}}
            @if($savedFoto || $newFile || (! $readonly && ! $isClosed && $fotoModel !== ''))
            <div wire:key="nc-foto-{{ $rowKey }}" class="px-4 py-3 border-t border-gray-100 dark:border-gray-700/60">
                <div class="flex items-center gap-1.5 mb-2">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Foto Bukti Temuan</span>
                </div>

                @if($newFile)
                    {{-- Foto baru terpilih — menggantikan foto before lama saat disimpan --}}
                    <div class="relative w-full h-32 bg-gray-50 dark:bg-gray-900/50 rounded-lg flex items-center justify-center p-1.5">
                        <img src="{{ $cropPreview?->temporaryUrl() ?? $newFile->temporaryUrl() }}" alt="Pratinjau foto bukti"
                            x-on:load="pendingCrops[@js($rowKey)] = false"
                            class="max-w-full max-h-full object-contain rounded">
                        <div x-show="pendingCrops[@js($rowKey)]" x-cloak
                            class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-1.5 bg-gray-100/80 dark:bg-gray-900/70 rounded-lg">
                            <svg class="animate-spin w-5 h-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">Memproses crop...</span>
                        </div>
                        <div class="absolute top-1.5 right-1.5 flex items-center gap-1 bg-white/90 dark:bg-gray-800/90 rounded-lg shadow-sm px-1 py-0.5">
                            @if($fotos[$rowKey]['is_cropped'] ?? false)
                                <span class="text-[10px] bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded">Cropped</span>
                            @endif
                            <button type="button" wire:click="openNcCropper({{ $argsPrefix }}'{{ $rowKey }}')"
                                wire:loading.attr="disabled" wire:target="openNcCropper({{ $argsPrefix }}'{{ $rowKey }}')"
                                class="text-blue-500 hover:text-blue-700 p-1 disabled:opacity-50" title="Crop foto">
                                <svg wire:loading.class="hidden" wire:target="openNcCropper({{ $argsPrefix }}'{{ $rowKey }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 3v14a1 1 0 001 1h13M3 7h14a1 1 0 011 1v13"/>
                                </svg>
                                <svg wire:loading wire:target="openNcCropper({{ $argsPrefix }}'{{ $rowKey }}')" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                            </button>
                            <button type="button" wire:click="removeNcFoto({{ $argsPrefix }}'{{ $rowKey }}')"
                                wire:loading.attr="disabled" wire:target="removeNcFoto({{ $argsPrefix }}'{{ $rowKey }}')"
                                class="text-red-500 hover:text-red-700 p-1 disabled:opacity-50" title="Batalkan foto">
                                <svg wire:loading.class="hidden" wire:target="removeNcFoto({{ $argsPrefix }}'{{ $rowKey }}')" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <svg wire:loading wire:target="removeNcFoto({{ $argsPrefix }}'{{ $rowKey }}')" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-[11px] text-gray-400 dark:text-gray-500 truncate" title="{{ $newFile->getClientOriginalName() }}">
                        {{ $newFile->getClientOriginalName() }} — {{ $newFile->getSize() >= 1048576 ? number_format($newFile->getSize() / 1048576, 2).' MB' : number_format($newFile->getSize() / 1024, 0).' KB' }} (hasil kompresi){{ $savedFoto ? ' — menggantikan foto bukti lama saat disimpan' : '' }}
                    </p>
                @endif

                {{-- Foto tersimpan (thumbnail) — disembunyikan saat ada foto baru pengganti --}}
                @if(! $newFile && $savedFoto)
                        @php $f = $savedFoto; @endphp
                        <div class="flex flex-wrap gap-2 mb-2">
                                <div wire:key="ncf-{{ $f->id }}"
                                    title="{{ $f->file_name }}{{ $f->file_size ? ' — '.($f->file_size >= 1048576 ? number_format($f->file_size / 1048576, 2).' MB' : number_format($f->file_size / 1024, 0).' KB') : '' }}"
                                    class="relative w-24 h-20 rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-gray-50 dark:bg-gray-900/50 flex items-center justify-center group">
                                    @if($f->isFileProcessing())
                                        <svg class="animate-spin w-5 h-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    @elseif($f->isFileFailed())
                                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" title="{{ $f->file_error }}">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    @elseif($f->isFileCompleted() && $f->isImage())
                                        <img x-show="thumbs[{{ $f->id }}]" x-cloak :src="thumbs[{{ $f->id }}]" alt="{{ $f->file_name }}" class="w-full h-full object-cover">
                                        <svg x-show="!thumbs[{{ $f->id }}]" class="animate-spin w-4 h-4 text-blue-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    @else
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    @endif
                                    @if($f->hasFile() && $f->isFileCompleted())
                                        <div class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-0.5 py-0.5 bg-white/90 dark:bg-gray-800/90 opacity-0 group-hover:opacity-100 transition-opacity">
                                            @can('lampiranPreview', \App\Models\LaporanHarian::class)
                                                <button type="button" wire:click="openNcFotoPreview({{ $f->id }})"
                                                    wire:loading.attr="disabled" wire:target="openNcFotoPreview({{ $f->id }})"
                                                    class="text-emerald-500 hover:text-emerald-700 p-0.5 disabled:opacity-50" title="Preview {{ $f->file_name }}">
                                                    <svg wire:loading.class="hidden" wire:target="openNcFotoPreview({{ $f->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="openNcFotoPreview({{ $f->id }})" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                    </svg>
                                                </button>
                                            @endcan
                                            @can('lampiranDownload', \App\Models\LaporanHarian::class)
                                                <button type="button" wire:click="downloadNcFoto({{ $f->id }})"
                                                    wire:loading.attr="disabled" wire:target="downloadNcFoto({{ $f->id }})"
                                                    class="text-blue-500 hover:text-blue-700 p-0.5 disabled:opacity-50" title="Download {{ $f->file_name }}">
                                                    <svg wire:loading.class="hidden" wire:target="downloadNcFoto({{ $f->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="downloadNcFoto({{ $f->id }})" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                    </svg>
                                                </button>
                                            @endcan
                                            @unless($readonly || $isClosed)
                                                <button type="button" wire:click="confirmDeleteNcFoto({{ $f->id }})"
                                                    wire:loading.attr="disabled" wire:target="confirmDeleteNcFoto({{ $f->id }})"
                                                    class="text-red-500 hover:text-red-700 p-0.5 disabled:opacity-50" title="Hapus foto">
                                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteNcFoto({{ $f->id }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                    <svg wire:loading wire:target="confirmDeleteNcFoto({{ $f->id }})" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                    </svg>
                                                </button>
                                            @endunless
                                        </div>
                                    @endif
                                </div>
                        </div>
                @endif

                {{-- Dropzone foto baru (form saja, NC belum closed) --}}
                @unless($readonly || $isClosed || $newFile || $fotoModel === '')
                    <div x-data="{ uploading: false, progress: 0,
                            async compressAndUpload(input) {
                                const file = await window.lhCompressUpload(input);
                                if (!file) return;
                                this.uploading = true;
                                this.progress = 0;
                                $wire.upload('{{ $fotoModel }}.{{ $rowKey }}.file', file,
                                    () => { this.uploading = false; this.progress = 0 },
                                    () => { this.uploading = false; this.progress = 0 },
                                    (e) => { this.progress = e.detail.progress },
                                );
                            }
                        }">
                        <label class="flex items-center justify-center gap-2 w-full h-14 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-white dark:bg-gray-800">
                            <div x-show="!uploading" class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                </svg>
                                <span>{{ $savedFoto ? 'Ganti foto' : 'Unggah foto bukti' }}</span>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500">Maks. {{ number_format(get_max_upload_size('foto_kapal') / 1024, 0) }} MB — dikompres otomatis</span>
                            </div>
                            <div x-show="uploading" x-cloak class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-xs text-blue-600" x-text="progress + '%'"></span>
                            </div>
                            <input type="file" class="hidden" x-on:change="compressAndUpload($event.target)"
                                accept=".{{ implode(',.', get_allowed_mimes_array('foto_kapal')) }}">
                        </label>
                    </div>
                @endunless

                @if($fotoError)
                    <p class="text-red-500 text-xs mt-1.5">{{ $fotoError }}</p>
                @endif
            </div>
            @endif
        </div>
    @empty
        <div class="py-8 text-center bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <svg class="h-10 w-10 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $readonly ? 'Tidak ada NC pada laporan ini.' : 'Tidak ada temuan NC — klik "Tambah NC" bila ada ketidaksesuaian.' }}
            </p>
        </div>
    @endforelse
    </div>
</div>
