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


                            @if($tipeEnum->value === 'harian')
                                {{-- A. Kondisi Cuaca --}}
                                <div class="md:col-span-2 mt-6">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">A. Kondisi Cuaca</h4>

                                {{-- Pagi --}}
                        <div class="md:col-span-2 mb-4">
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
                        <div class="md:col-span-2 mb-4">
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
                        <div class="md:col-span-2 mb-4">
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

                                        {{-- Estimasi Suhu Lingkungan --}}
                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                                Estimasi Suhu Lingkungan (°C)
                                            </label>
                                            <input wire:model="items.{{ $index }}.suhu" type="number" step="0.01"
                                                placeholder="Contoh: 30"
                                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                            @error("items.{$index}.suhu") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- B. Personel --}}
                                <div class="md:col-span-2 mt-6">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">B. Personel</h4>
                                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">No</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jabatan</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($items[$index]['personel'] ?? [] as $pIndex => $personel)
                                                <tr wire:key="personel-{{ $index }}-{{ $pIndex }}">
                                                    <td class="px-3 py-2 text-sm text-center text-gray-900 dark:text-gray-100">{{ $pIndex + 1 }}</td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.personel.{{ $pIndex }}.jabatan"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Jabatan">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.personel.{{ $pIndex }}.status"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Status">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.personel.{{ $pIndex }}.keterangan"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Keterangan">
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <button type="button" 
                                                            wire:click="confirmRemovePersonel({{ $index }}, {{ $pIndex }})"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                        <div class="flex justify-end mt-3">
                                            <button type="button" wire:click="addPersonel({{ $index }})" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- C. Peralatan --}}
                                <div class="md:col-span-2 mt-6">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">C. Peralatan</h4>
                                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">No</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Jumlah</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($items[$index]['peralatan'] ?? [] as $pIndex => $peralatan)
                                                <tr wire:key="peralatan-{{ $index }}-{{ $pIndex }}">
                                                    <td class="px-3 py-2 text-sm text-center text-gray-900 dark:text-gray-100">{{ $pIndex + 1 }}</td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.peralatan.{{ $pIndex }}.jenis"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Jenis">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="number" wire:model="items.{{ $index }}.peralatan.{{ $pIndex }}.jumlah" min="1"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Jumlah">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.peralatan.{{ $pIndex }}.keterangan"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Keterangan">
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <button type="button" 
                                                            wire:click="confirmRemovePeralatan({{ $index }}, {{ $pIndex }})"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                        <div class="flex justify-end mt-3">
                                            <button type="button" wire:click="addPeralatan({{ $index }})" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- D. Consumable dan Material --}}
                                <div class="md:col-span-2 mt-6">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">D. Consumable dan Material</h4>
                                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead class="bg-gray-50 dark:bg-gray-900">
                                                <tr>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">No</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-32">Jumlah</th>
                                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Keterangan</th>
                                                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($items[$index]['consumable'] ?? [] as $cIndex => $consumable)
                                                <tr wire:key="consumable-{{ $index }}-{{ $cIndex }}">
                                                    <td class="px-3 py-2 text-sm text-center text-gray-900 dark:text-gray-100">{{ $cIndex + 1 }}</td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.consumable.{{ $cIndex }}.jenis"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Jenis">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="number" wire:model="items.{{ $index }}.consumable.{{ $cIndex }}.jumlah" min="1"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Jumlah">
                                                    </td>
                                                    <td class="px-3 py-2">
                                                        <input type="text" wire:model="items.{{ $index }}.consumable.{{ $cIndex }}.keterangan"
                                                            class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                            placeholder="Keterangan">
                                                    </td>
                                                    <td class="px-3 py-2 text-center">
                                                        <button type="button" 
                                                            wire:click="confirmRemoveConsumable({{ $index }}, {{ $cIndex }})"
                                                            class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        </div>
                                        <div class="flex justify-end mt-3">
                                            <button type="button" wire:click="addConsumable({{ $index }})" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- E. Uraian Aktivitas --}}
                                <div class="md:col-span-2 mt-6">
                                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                                        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">E. Uraian Aktivitas</h4>
                                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-900">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-12">No</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aktivitas</th>
                                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-48">PIC</th>
                                                        <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider w-16">Aksi</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($items[$index]['aktivitas'] ?? [] as $aIndex => $aktivitas)
                                                    <tr wire:key="aktivitas-{{ $index }}-{{ $aIndex }}">
                                                        <td class="px-3 py-2 text-sm text-center text-gray-900 dark:text-gray-100">{{ $aIndex + 1 }}</td>
                                                        <td class="px-3 py-2">
                                                            <textarea wire:model="items.{{ $index }}.aktivitas.{{ $aIndex }}.aktivitas" rows="2"
                                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                                placeholder="Deskripsi aktivitas"></textarea>
                                                        </td>
                                                        <td class="px-3 py-2">
                                                            <input type="text" wire:model="items.{{ $index }}.aktivitas.{{ $aIndex }}.pic"
                                                                class="w-full px-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white" 
                                                                placeholder="PIC">
                                                        </td>
                                                        <td class="px-3 py-2 text-center">
                                                            <button type="button" 
                                                                wire:click="confirmRemoveAktivitas({{ $index }}, {{ $aIndex }})"
                                                                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                                </svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="flex justify-end mt-3">
                                            <button type="button" wire:click="addAktivitas({{ $index }})" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                                </svg>
                                                Tambah
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Lampiran Section - Paling Bawah --}}
                            <div class="md:col-span-2 mt-4">
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2 mb-3">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                        </svg>
                                        Lampiran
                                        <span class="text-xs font-normal text-gray-400">(opsional — {{ get_upload_config_display('foto_kapal') }})</span>
                                    </h4>

                                    {{-- Lampiran Cards --}}
                                    <div class="space-y-3">
                                        @foreach($lampiran[$index] ?? [] as $lampiranIndex => $lampiranItem)
                                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 border border-gray-200 dark:border-gray-700" wire:key="lampiran-{{ $index }}-{{ $lampiranIndex }}">
                                                <div class="flex items-start gap-3">
                                                    {{-- File Upload Area --}}
                                                    <div class="flex-1">
                                                        @if(isset($lampiranItem['file']) && $lampiranItem['file'])
                                                            {{-- File Selected --}}
                                                            <div class="flex items-center gap-2 px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                                                                <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                                                </svg>
                                                                <span class="text-xs text-blue-700 dark:text-blue-300 truncate flex-1">{{ $lampiranItem['file']->getClientOriginalName() }}</span>
                                                                <span class="text-xs text-blue-500 dark:text-blue-400">{{ number_format($lampiranItem['file']->getSize() / 1024, 0) }} KB</span>
                                                                @if($lampiranItem['is_cropped'] ?? false)
                                                                    <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded">Cropped</span>
                                                                @endif
                                                                @php
                                                                    $ext = strtolower($lampiranItem['file']->getClientOriginalExtension());
                                                                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp']);
                                                                @endphp
                                                                @if($isImage)
                                                                    {{-- Crop Button --}}
                                                                    <button type="button" wire:click="openCropper({{ $index }}, {{ $lampiranIndex }})"
                                                                        wire:loading.attr="disabled"
                                                                        wire:target="openCropper({{ $index }}, {{ $lampiranIndex }})"
                                                                        class="text-blue-500 hover:text-blue-700 p-0.5 disabled:opacity-50" title="Crop gambar">
                                                                        <svg wire:loading.class="hidden" wire:target="openCropper({{ $index }}, {{ $lampiranIndex }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                                                        </svg>
                                                                        <svg wire:loading wire:target="openCropper({{ $index }}, {{ $lampiranIndex }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                    </button>
                                                                @endif
                                                                <button type="button" wire:click="removeLampiran({{ $index }}, {{ $lampiranIndex }})"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="removeLampiran({{ $index }}, {{ $lampiranIndex }})"
                                                                    class="text-red-500 hover:text-red-700 p-0.5 disabled:opacity-50" title="Hapus">
                                                                    <svg wire:loading.class="hidden" wire:target="removeLampiran({{ $index }}, {{ $lampiranIndex }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                                    </svg>
                                                                    <svg wire:loading wire:target="removeLampiran({{ $index }}, {{ $lampiranIndex }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                    </svg>
                                                                </button>
                                                            </div>
                                                        @else
                                                            {{-- Upload Area --}}
                                                            <div x-data="{ uploading: false, progress: 0 }"
                                                                 x-on:livewire-upload-start="uploading = true"
                                                                 x-on:livewire-upload-finish="uploading = false; progress = 0"
                                                                 x-on:livewire-upload-cancel="uploading = false"
                                                                 x-on:livewire-upload-error="uploading = false"
                                                                 x-on:livewire-upload-progress="progress = $event.detail.progress">
                                                                <label class="flex flex-col items-center justify-center w-full px-3 py-3 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg cursor-pointer hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-white dark:bg-gray-800">
                                                                    <div x-show="!uploading" class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                                                        </svg>
                                                                        <span>Klik untuk upload</span>
                                                                    </div>
                                                                    <div x-show="uploading" x-cloak class="flex items-center justify-center gap-2">
                                                                        <svg class="animate-spin w-3 h-3 text-blue-500" fill="none" viewBox="0 0 24 24">
                                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                                        </svg>
                                                                        <span class="text-xs text-blue-600" x-text="progress + '%'"></span>
                                                                    </div>
                                                                    <input type="file" wire:model="lampiran.{{ $index }}.{{ $lampiranIndex }}.file" class="hidden"
                                                                        accept=".{{ implode(',.', get_allowed_mimes_array('foto_kapal')) }}">
                                                                </label>
                                                            </div>
                                                        @endif
                                                        @error("lampiran.{$index}.{$lampiranIndex}.file") <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                                    </div>

                                                    {{-- Remove Button (if no file) --}}
                                                    @if(!isset($lampiranItem['file']) || !$lampiranItem['file'])
                                                        <button type="button" wire:click="confirmRemoveLampiran({{ $index }}, {{ $lampiranIndex }})"
                                                            class="text-red-400 hover:text-red-600 p-1" title="Hapus lampiran">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                </div>

                                                {{-- Keterangan --}}
                                                <div class="mt-2">
                                                    <input type="text" wire:model="lampiran.{{ $index }}.{{ $lampiranIndex }}.keterangan"
                                                        placeholder="Keterangan lampiran..."
                                                        class="w-full text-xs px-2 py-1.5 border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-800 dark:text-white">
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    {{-- Button Tambah di Bawah --}}
                                    <div class="mt-3">
                                        <button type="button" wire:click="addLampiran({{ $index }})"
                                            wire:loading.attr="disabled"
                                            wire:target="addLampiran({{ $index }})"
                                            class="w-full flex items-center justify-center gap-2 px-3 py-2 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg text-xs font-medium text-gray-500 dark:text-gray-400 hover:border-blue-400 hover:text-blue-500 dark:hover:border-blue-500 dark:hover:text-blue-400 transition-colors bg-white dark:bg-gray-800">
                                            <svg wire:loading.class="hidden" wire:target="addLampiran({{ $index }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                            <svg wire:loading wire:target="addLampiran({{ $index }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Tambah Lampiran
                                        </button>
                                    </div>
                                </div>
                            </div>
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

    {{-- Delete Lampiran Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteLampiranModal"
        wire:model="showDeleteLampiranModal"
        title="Hapus Lampiran"
        message="Apakah Anda yakin ingin menghapus lampiran ini?"
        confirmMethod="removeLampiranConfirmed"
    />

    {{-- Delete Personel Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeletePersonelModal"
        wire:model="showDeletePersonelModal"
        title="Hapus Personel"
        message="Apakah Anda yakin ingin menghapus data personel ini?"
        confirmMethod="removePersonelConfirmed"
    />

    {{-- Delete Peralatan Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeletePeralatanModal"
        wire:model="showDeletePeralatanModal"
        title="Hapus Peralatan"
        message="Apakah Anda yakin ingin menghapus data peralatan ini?"
        confirmMethod="removePeralatanConfirmed"
    />

    {{-- Delete Consumable Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteConsumableModal"
        wire:model="showDeleteConsumableModal"
        title="Hapus Consumable"
        message="Apakah Anda yakin ingin menghapus data consumable ini?"
        confirmMethod="removeConsumableConfirmed"
    />

    {{-- Delete Aktivitas Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteAktivitasModal"
        wire:model="showDeleteAktivitasModal"
        title="Hapus Aktivitas"
        message="Apakah Anda yakin ingin menghapus data aktivitas ini?"
        confirmMethod="removeAktivitasConfirmed"
    />

    {{-- Image Cropper Modal --}}
    <x-image-crop-modal 
        :show="$showCropperModal" 
        :imageUrl="$croppingImageUrl" 
        :cropData="$cropData" 
    />
</div>
