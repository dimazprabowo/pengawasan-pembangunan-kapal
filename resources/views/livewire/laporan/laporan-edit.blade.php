<div>
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <a href="{{ route('laporan.index') }}" wire:navigate
                x-data="{ loading: false }" x-on:click="loading = true"
                x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </a>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Edit Laporan {{ $tipeEnum->label() }}</h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Perbarui data laporan di bawah ini</p>
    </div>

    <form wire:submit="save">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 mb-6">
            {{-- Card Header --}}
            <div class="px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Detail Laporan</h3>
                    <span class="text-xs text-gray-400 dark:text-gray-500">
                        Dibuat {{ $laporan->created_at->translatedFormat('d M Y H:i') }}
                    </span>
                </div>
            </div>

            {{-- Card Body --}}
            <div class="p-5">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Jenis Kapal --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Jenis Kapal <span class="text-red-500">*</span>
                        </label>
                        <x-searchable-select
                            wire:model.live="jenis_kapal_id"
                            :options="$jenisKapalList->map(fn($jk) => [
                                'value' => $jk->id,
                                'label' => $jk->nama . ($jk->company ? ' (' . $jk->company->name . ')' : '') . ($jk->galangan ? ' - (' . $jk->galangan->nama . ')' : '')
                            ])->toArray()"
                            placeholder="Pilih jenis kapal"
                            searchPlaceholder="Cari jenis kapal..."
                            :error="$errors->has('jenis_kapal_id')"
                        />
                        @error('jenis_kapal_id') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                        @if($jenis_kapal_id)
                            @php
                                $selectedJenisKapal = $jenisKapalList->firstWhere('id', $jenis_kapal_id);
                            @endphp
                            @if($selectedJenisKapal)
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-blue-700 dark:text-blue-300">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="font-medium">{{ $selectedJenisKapal->nama }}</span>
                                    </div>
                                    @if($selectedJenisKapal->company)
                                        <span class="text-blue-600 dark:text-blue-400">•</span>
                                        <span>{{ $selectedJenisKapal->company->name }}</span>
                                    @endif
                                    @if($selectedJenisKapal->galangan)
                                        <span class="text-blue-600 dark:text-blue-400">•</span>
                                        <span>{{ $selectedJenisKapal->galangan->nama }}</span>
                                    @endif
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Judul --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Judul <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="judul" type="text" required
                            placeholder="Masukkan judul laporan"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        @error('judul') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Tanggal --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Tanggal Laporan <span class="text-red-500">*</span>
                        </label>
                        <input wire:model="tanggal_laporan" type="date" required
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                        @error('tanggal_laporan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- File Upload --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Lampiran <span class="text-gray-400 text-xs font-normal">(opsional — {{ get_upload_config_display() }})</span>
                        </label>

                        {{-- New file selected --}}
                        @if($file)
                            <div class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm text-blue-700 dark:text-blue-300 truncate flex-1">{{ $file->getClientOriginalName() }}</span>
                                <span class="text-xs text-blue-500 dark:text-blue-400">{{ number_format($file->getSize() / 1024, 0) }} KB</span>
                                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">File baru</span>
                                <button type="button" wire:click="removeNewFile"
                                    wire:loading.attr="disabled"
                                    wire:target="removeNewFile"
                                    class="text-red-500 hover:text-red-700 p-0.5 rounded transition-colors disabled:opacity-50" title="Batal upload">
                                    <svg wire:loading.class="hidden" wire:target="removeNewFile" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <svg wire:loading wire:target="removeNewFile" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </div>
                        
                        {{-- File processing status --}}
                        @elseif($laporan->file_status === 'pending' || $laporan->file_status === 'processing')
                            <x-file-status-indicator :laporan="$laporan" :queueStatus="$queueStatus" />
                        
                        {{-- File failed status --}}
                        @elseif($laporan->file_status === 'failed')
                            <x-file-status-indicator :laporan="$laporan" :queueStatus="$queueStatus" />
                        
                        {{-- Existing completed file --}}
                        @elseif($laporan->file_name && $laporan->file_path)
                            <div class="flex items-center gap-3 px-3 py-2.5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg">
                                <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <span class="text-sm text-emerald-700 dark:text-emerald-300 truncate flex-1">{{ $laporan->file_name }}</span>
                                <span class="text-xs text-emerald-500 dark:text-emerald-400">{{ number_format($laporan->file_size / 1024, 0) }} KB</span>
                                <a href="{{ route('laporan.preview', $laporan) }}" target="_blank"
                                    x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => loading = false, 2000)"
                                    x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                                    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 p-0.5 rounded transition-colors" title="Preview file">
                                    <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </a>
                                @can('laporan_download')
                                    <a href="{{ route('laporan.download', $laporan) }}"
                                        x-data="{ loading: false }" x-on:click="loading = true; setTimeout(() => loading = false, 2000)"
                                        x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                                        class="text-blue-500 hover:text-blue-700 p-0.5 rounded transition-colors" title="Download file">
                                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                        </svg>
                                        <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </a>
                                @endcan
                                <button type="button" wire:click="confirmDeleteFile"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmDeleteFile"
                                    class="text-red-500 hover:text-red-700 p-0.5 rounded transition-colors disabled:opacity-50" title="Hapus file">
                                    <svg wire:loading.class="hidden" wire:target="confirmDeleteFile" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    <svg wire:loading wire:target="confirmDeleteFile" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </div>
                        
                        {{-- Upload area --}}
                        @else
                            <div x-data="{ uploading: false, progress: 0 }"
                                 x-on:livewire-upload-start="uploading = true"
                                 x-on:livewire-upload-finish="uploading = false; progress = 0"
                                 x-on:livewire-upload-cancel="uploading = false"
                                 x-on:livewire-upload-error="uploading = false"
                                 x-on:livewire-upload-progress="progress = $event.detail.progress">
                                <label class="flex flex-col items-center justify-center w-full px-4 py-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-gray-50 dark:bg-gray-700/50">
                                    <div x-show="!uploading" class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                        </svg>
                                        <span>Klik untuk upload atau drag & drop</span>
                                    </div>
                                    <div x-show="uploading" x-cloak class="w-full">
                                        <div class="flex items-center gap-2 mb-1">
                                            <svg class="animate-spin w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span class="text-sm text-blue-600 dark:text-blue-400" x-text="'Mengupload... ' + progress + '%'"></span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1.5">
                                            <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + progress + '%'"></div>
                                        </div>
                                    </div>
                                    <input type="file" wire:model="file" class="hidden"
                                        accept=".{{ implode(',.',  get_allowed_mimes_array()) }}">
                                </label>
                            </div>
                        @endif
                        @error('file') <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    {{-- Isi --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Isi Laporan <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                        </label>
                        <textarea wire:model="isi" rows="4"
                            placeholder="Tulis isi laporan di sini..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        @error('isi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    {{-- Catatan --}}
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Catatan <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                        </label>
                        <textarea wire:model="catatan" rows="2"
                            placeholder="Catatan tambahan..."
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                        @error('catatan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    </div>

                    @if($tipeEnum->value === 'harian')
                        {{-- Suhu --}}
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Suhu (°C) <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                            </label>
                            <input wire:model="suhu" type="number" step="0.01"
                                placeholder="Contoh: 28.5"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                            @error('suhu') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        {{-- Weather Section Title --}}
                        <div class="md:col-span-2 mt-4">
                            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600 pb-2">
                                Kondisi Cuaca & Kelembaban
                            </h4>
                        </div>

                        {{-- Pagi --}}
                        <div class="md:col-span-2">
                            <div class="bg-orange-50 dark:bg-orange-900/10 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
                                <h5 class="text-sm font-medium text-orange-800 dark:text-orange-400 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Pagi
                                </h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                                        <x-searchable-select
                                            wire:model="cuaca_pagi_id"
                                            :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                            placeholder="Pilih cuaca"
                                            searchPlaceholder="Cari cuaca..."
                                        />
                                        @error('cuaca_pagi_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                        <x-searchable-select
                                            wire:model="kelembaban_pagi_id"
                                            :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                            placeholder="Pilih kelembaban"
                                            searchPlaceholder="Cari kelembaban..."
                                        />
                                        @error('kelembaban_pagi_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Siang --}}
                        <div class="md:col-span-2">
                            <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
                                <h5 class="text-sm font-medium text-yellow-800 dark:text-yellow-400 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    Siang
                                </h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                                        <x-searchable-select
                                            wire:model="cuaca_siang_id"
                                            :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                            placeholder="Pilih cuaca"
                                            searchPlaceholder="Cari cuaca..."
                                        />
                                        @error('cuaca_siang_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                        <x-searchable-select
                                            wire:model="kelembaban_siang_id"
                                            :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                            placeholder="Pilih kelembaban"
                                            searchPlaceholder="Cari kelembaban..."
                                        />
                                        @error('kelembaban_siang_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Sore --}}
                        <div class="md:col-span-2">
                            <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
                                <h5 class="text-sm font-medium text-indigo-800 dark:text-indigo-400 mb-3 flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                                    </svg>
                                    Sore
                                </h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                                        <x-searchable-select
                                            wire:model="cuaca_sore_id"
                                            :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                            placeholder="Pilih cuaca"
                                            searchPlaceholder="Cari cuaca..."
                                        />
                                        @error('cuaca_sore_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                        <x-searchable-select
                                            wire:model="kelembaban_sore_id"
                                            :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                            placeholder="Pilih kelembaban"
                                            searchPlaceholder="Cari kelembaban..."
                                        />
                                        @error('kelembaban_sore_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-end gap-2 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <a href="{{ route('laporan.index') }}" wire:navigate
                x-data="{ loading: false }" x-on:click="loading = true"
                x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                class="inline-flex items-center justify-center gap-1.5 font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-blue-500 px-4 py-2 text-sm w-full sm:w-auto">
                <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span x-show="!loading">Batal</span>
                <span x-show="loading" x-cloak>Memuat...</span>
            </a>
            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                loadingText="Menyimpan..." class="w-full sm:w-auto">
                Update Laporan
            </x-loading-button>
        </div>
    </form>

    {{-- Delete File Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteFileModal"
        wire:model="showDeleteFileModal"
        title="Hapus File Lampiran"
        message="Apakah Anda yakin ingin menghapus file"
        :itemName="$laporan->file_name"
        confirmMethod="deleteFile"
    />
</div>
