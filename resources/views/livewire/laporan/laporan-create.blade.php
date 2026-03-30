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
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Tambah Laporan {{ $tipeEnum->label() }}</h2>
        </div>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Anda dapat menambahkan beberapa laporan sekaligus</p>
    </div>

    <form wire:submit="save">
        {{-- Jenis Kapal Selection --}}
        <div class="mb-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
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

        {{-- Cards --}}
        <div class="space-y-4 mb-6">
            @foreach($items as $index => $item)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700"
                     wire:key="item-{{ $index }}">
                    {{-- Card Header --}}
                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Laporan #{{ $index + 1 }}
                        </h3>
                        @if(count($items) > 1)
                            <button type="button" wire:click="confirmRemoveItem({{ $index }})"
                                wire:loading.attr="disabled"
                                wire:target="confirmRemoveItem({{ $index }})"
                                class="text-red-500 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300 transition-colors p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50"
                                title="Hapus laporan ini">
                                <svg wire:loading.class="hidden" wire:target="confirmRemoveItem({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <svg wire:loading wire:target="confirmRemoveItem({{ $index }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        @endif
                    </div>

                    {{-- Card Body --}}
                    <div class="p-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {{-- Judul --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Judul <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="items.{{ $index }}.judul" type="text" required
                                    placeholder="Masukkan judul laporan"
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                @error("items.{$index}.judul") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Tanggal --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Tanggal Laporan <span class="text-red-500">*</span>
                                </label>
                                <input wire:model="items.{{ $index }}.tanggal_laporan" type="date" required
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                @error("items.{$index}.tanggal_laporan") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- File Upload --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Lampiran <span class="text-gray-400 text-xs font-normal">(opsional — {{ get_upload_config_display() }})</span>
                                </label>
                                @if(isset($files[$index]) && $files[$index])
                                    <div class="flex items-center gap-3 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <span class="text-sm text-blue-700 dark:text-blue-300 truncate flex-1">{{ $files[$index]->getClientOriginalName() }}</span>
                                        <span class="text-xs text-blue-500 dark:text-blue-400">{{ number_format($files[$index]->getSize() / 1024, 0) }} KB</span>
                                        <button type="button" wire:click="removeFile({{ $index }})"
                                            wire:loading.attr="disabled"
                                            wire:target="removeFile({{ $index }})"
                                            class="text-red-500 hover:text-red-700 p-0.5 rounded transition-colors disabled:opacity-50" title="Hapus file">
                                            <svg wire:loading.class="hidden" wire:target="removeFile({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            <svg wire:loading wire:target="removeFile({{ $index }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    </div>
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
                                            <input type="file" wire:model="files.{{ $index }}" class="hidden"
                                                accept=".{{ implode(',.',  get_allowed_mimes_array()) }}">
                                        </label>
                                    </div>
                                @endif
                                @error("files.{$index}") <span class="text-red-500 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>

                            {{-- Isi --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Isi Laporan <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                                </label>
                                <textarea wire:model="items.{{ $index }}.isi" rows="3"
                                    placeholder="Tulis isi laporan di sini..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                @error("items.{$index}.isi") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            {{-- Catatan --}}
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Catatan <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                                </label>
                                <textarea wire:model="items.{{ $index }}.catatan" rows="2"
                                    placeholder="Catatan tambahan..."
                                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"></textarea>
                                @error("items.{$index}.catatan") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>

                            @if($tipeEnum->value === 'harian')
                                {{-- Suhu --}}
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Suhu (°C) <span class="text-gray-400 text-xs font-normal">(opsional)</span>
                                    </label>
                                    <input wire:model="items.{{ $index }}.suhu" type="number" step="0.01"
                                        placeholder="Contoh: 28.5"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                    @error("items.{$index}.suhu") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
                                                    wire:model="items.{{ $index }}.cuaca_pagi_id"
                                                    :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                                    placeholder="Pilih cuaca"
                                                    searchPlaceholder="Cari cuaca..."
                                                />
                                                @error("items.{$index}.cuaca_pagi_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                                <x-searchable-select
                                                    wire:model="items.{{ $index }}.kelembaban_pagi_id"
                                                    :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                                    placeholder="Pilih kelembaban"
                                                    searchPlaceholder="Cari kelembaban..."
                                                />
                                                @error("items.{$index}.kelembaban_pagi_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
                                                    wire:model="items.{{ $index }}.cuaca_siang_id"
                                                    :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                                    placeholder="Pilih cuaca"
                                                    searchPlaceholder="Cari cuaca..."
                                                />
                                                @error("items.{$index}.cuaca_siang_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                                <x-searchable-select
                                                    wire:model="items.{{ $index }}.kelembaban_siang_id"
                                                    :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                                    placeholder="Pilih kelembaban"
                                                    searchPlaceholder="Cari kelembaban..."
                                                />
                                                @error("items.{$index}.kelembaban_siang_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
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
                                                    wire:model="items.{{ $index }}.cuaca_sore_id"
                                                    :options="$cuacaList->map(fn($c) => ['value' => $c->id, 'label' => $c->nama])->toArray()"
                                                    placeholder="Pilih cuaca"
                                                    searchPlaceholder="Cari cuaca..."
                                                />
                                                @error("items.{$index}.cuaca_sore_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                                                <x-searchable-select
                                                    wire:model="items.{{ $index }}.kelembaban_sore_id"
                                                    :options="$kelembabanList->map(fn($k) => ['value' => $k->id, 'label' => $k->nama . ($k->nilai ? ' (' . $k->nilai . ')' : '')])->toArray()"
                                                    placeholder="Pilih kelembaban"
                                                    searchPlaceholder="Cari kelembaban..."
                                                />
                                                @error("items.{$index}.kelembaban_sore_id") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Add More Button (bottom) --}}
        <div class="mb-6">
            <button type="button" wire:click="addItem"
                wire:loading.attr="disabled"
                wire:target="addItem"
                class="w-full flex items-center justify-center gap-2 px-4 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-500 dark:hover:border-blue-500 dark:hover:text-blue-400 transition-colors bg-white dark:bg-gray-800/50 disabled:opacity-50">
                <svg wire:loading.class="hidden" wire:target="addItem" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <svg wire:loading wire:target="addItem" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Tambah Laporan Lainnya
            </button>
        </div>

        {{-- Footer Actions --}}
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Total: <span class="font-semibold text-gray-900 dark:text-white">{{ count($items) }}</span> laporan akan ditambahkan
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
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
                    Simpan Semua
                </x-loading-button>
            </div>
        </div>
    </form>

    {{-- Delete Card Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteCardModal"
        wire:model="showDeleteCardModal"
        title="Hapus Laporan"
        message="Apakah Anda yakin ingin menghapus card laporan"
        :itemName="$deletingCardIndex !== null ? '#' . ($deletingCardIndex + 1) : ''"
        confirmMethod="removeItem"
    />
</div>
