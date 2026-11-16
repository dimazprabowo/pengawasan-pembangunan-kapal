<div>
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari galangan..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <x-filter-popover :filters="['statusFilter']" :per-page="true">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Status</label>
                <x-searchable-select
                    wire:model.live="statusFilter"
                    :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                    placeholder="Semua Status"
                    searchPlaceholder="Cari status..."
                />
            </div>
        </x-filter-popover>

        <div class="flex items-center gap-2 flex-wrap">
            @can('galangan_export_excel')
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('galangan_export_pdf')
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
            @can('galangan_create')
                <x-loading-button wire:click="create" target="create" variant="primary" size="md" loadingText="Memuat..." class="flex-1 lg:flex-none">
                    <x-slot:icon>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </x-slot:icon>
                    Tambah Galangan
                </x-loading-button>
            @endcan
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <x-sortable-header field="nama" :sort-field="$sortField" :sort-dir="$sortDir">Galangan</x-sortable-header>
                        <x-sortable-header field="kota" :sort-field="$sortField" :sort-dir="$sortDir">Lokasi</x-sortable-header>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Kontak</th>
                        <x-sortable-header field="data_kapal_count" :sort-field="$sortField" :sort-dir="$sortDir">Data Kapal</x-sortable-header>
                        <x-sortable-header field="status" :sort-field="$sortField" :sort-dir="$sortDir">Status</x-sortable-header>
                        <x-sortable-header field="updated_at" :sort-field="$sortField" :sort-dir="$sortDir">Dibuat / Diubah</x-sortable-header>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($galanganList as $galangan)
                        <tr wire:key="galangan-{{ $galangan->id }}" class="hover:bg-green-50 dark:hover:bg-green-900/10 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ strtoupper(substr($galangan->nama, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $galangan->nama }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $galangan->kode }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $galangan->kota ?? '-' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $galangan->provinsi ?? '-' }}</div>
                                @if($galangan->hasLokasi())
                                    <div class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-blue-600 dark:text-blue-400" title="{{ $galangan->latitude }}, {{ $galangan->longitude }}">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                                        Titik peta tersedia
                                    </div>
                                @else
                                    <div class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                        Lokasi belum diatur
                                    </div>
                                @endif
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $galangan->pic_name ?? '-' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $galangan->telepon ?? '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400">
                                    {{ $galangan->data_kapal_count }} kapal
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @can('galangan_update')
                                    <x-toggle-switch wire:click="toggleStatus({{ $galangan->id }})"
                                        :active="$galangan->status->value === 'active'"
                                        target="toggleStatus({{ $galangan->id }})"
                                        activeColor="green"
                                        title="Aktifkan/Nonaktifkan" />
                                @else
                                    @php
                                        $statusColors = [
                                            'active' => 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400',
                                            'inactive' => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400',
                                        ];
                                    @endphp
                                    <span class="px-2 py-1 text-xs font-medium rounded-full {{ $statusColors[$galangan->status->value] ?? $statusColors['inactive'] }}">
                                        {{ $galangan->status->label() }}
                                    </span>
                                @endcan
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                <div>Dibuat: {{ $galangan->createdBy?->name ?? '—' }}</div>
                                <div class="text-gray-400 dark:text-gray-500">Diubah: {{ $galangan->updatedBy?->name ?? '—' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('galangan_update')
                                        <button wire:click="edit({{ $galangan->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="edit({{ $galangan->id }})"
                                            class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300 disabled:opacity-50"
                                            title="Edit">
                                            <svg wire:loading.class="hidden" wire:target="edit({{ $galangan->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg wire:loading wire:target="edit({{ $galangan->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                    @can('galangan_delete')
                                        <button wire:click="confirmDelete({{ $galangan->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmDelete({{ $galangan->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                            title="Hapus">
                                            <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $galangan->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDelete({{ $galangan->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada galangan ditemukan</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
            {{ $galanganList->links() }}
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-50 overflow-y-auto custom-scrollbar" x-data="{ show: @entangle('showModal') }">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full">
                    <form wire:submit="save">
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                                {{ $editMode ? 'Edit Galangan' : 'Tambah Galangan' }}
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kode Galangan <span class="text-red-500">*</span></label>
                                    <input wire:model="kode" type="text" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('kode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama Galangan <span class="text-red-500">*</span></label>
                                    <input wire:model="nama" type="text" required
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('nama') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Alamat</label>
                                    <textarea wire:model="alamat" rows="2"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    @error('alamat') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kota</label>
                                    <input wire:model="kota" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('kota') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Provinsi</label>
                                    <input wire:model="provinsi" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('provinsi') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                {{-- Lokasi peta: klik/drag pin, cari alamat (OSM), atau lokasi perangkat --}}
                                <div class="md:col-span-2" wire:ignore.self
                                     x-data="galanganLocationPicker({ lat: $wire.entangle('latitude'), lng: $wire.entangle('longitude') })">
                                    <div class="flex items-center justify-between mb-1">
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Lokasi di Peta</label>
                                        <span class="text-xs" :class="hasPoint ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400 dark:text-gray-500'"
                                              x-text="hasPoint ? 'Titik lokasi sudah dipilih' : 'Klik peta untuk menaruh pin'"></span>
                                    </div>

                                    <div class="rounded-lg border border-gray-300 dark:border-gray-600 overflow-hidden">
                                        <div class="flex flex-col sm:flex-row gap-2 p-2 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-600">
                                            <div class="relative flex-1" x-ref="searchBox" @click.outside="results = []">
                                                <div class="flex gap-2">
                                                    <input type="text" x-model="query" @keydown.enter.prevent="search()"
                                                        placeholder="Cari alamat / nama tempat, lalu tekan Enter"
                                                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
                                                    <button type="button" @click="search()" :disabled="searching"
                                                        class="inline-flex items-center justify-center w-10 shrink-0 rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-60 transition-colors"
                                                        title="Cari lokasi" aria-label="Cari lokasi">
                                                        <svg x-show="!searching" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                                        <svg x-show="searching" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                                    </button>
                                                </div>
                                                <ul x-show="results.length" x-cloak
                                                    :class="dropUp ? 'bottom-full mb-2' : 'top-full mt-2'"
                                                    class="absolute z-[1000] left-0 right-0 max-h-60 overflow-y-auto custom-scrollbar bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg divide-y divide-gray-100 dark:divide-gray-700">
                                                    <template x-for="(r, i) in results" :key="i">
                                                        <li>
                                                            <button type="button" @click="pick(r)"
                                                                class="w-full text-left px-3 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-blue-50 dark:hover:bg-blue-900/20 flex gap-2">
                                                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/></svg>
                                                                <span x-text="r.label"></span>
                                                            </button>
                                                        </li>
                                                    </template>
                                                </ul>
                                            </div>
                                            <div class="flex gap-2">
                                                <button type="button" @click="locate()" :disabled="locating"
                                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 disabled:opacity-60 transition-colors"
                                                    title="Gunakan lokasi perangkat saat ini">
                                                    <svg :class="locating && 'animate-pulse'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2v3m0 14v3M2 12h3m14 0h3M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                                                    <span class="hidden sm:inline">Lokasi Saya</span>
                                                </button>
                                                <button type="button" @click="clear()" x-show="hasPoint" x-cloak
                                                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-lg border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 bg-white dark:bg-gray-700 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                                    title="Hapus titik lokasi">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    <span class="hidden sm:inline">Hapus Titik</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="relative isolate">
                                            <div wire:ignore x-ref="map" class="h-64 w-full z-0"></div>
                                            <div class="absolute top-2 right-2 z-[500] flex rounded-lg overflow-hidden shadow border border-gray-200 dark:border-gray-600 text-[11px] font-medium" x-data="{ m: 'peta' }">
                                                <button type="button" @click="m = 'peta'; setMode('peta')" :class="m === 'peta' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-2.5 py-1">Peta</button>
                                                <button type="button" @click="m = 'satellite'; setMode('satellite')" :class="m === 'satellite' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-2.5 py-1">Satelit</button>
                                            </div>
                                        </div>
                                    </div>
                                    <p x-show="message" x-cloak x-text="message" class="mt-1 text-xs text-amber-600 dark:text-amber-400"></p>

                                    <div class="grid grid-cols-2 gap-3 mt-3">
                                        <div>
                                            <x-input-label for="latitude" value="Latitude" />
                                            <x-text-input id="latitude" type="number" step="any" x-model="lat" class="mt-1 block w-full" placeholder="Contoh: -7.2050000" />
                                            @error('latitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <x-input-label for="longitude" value="Longitude" />
                                            <x-text-input id="longitude" type="number" step="any" x-model="lng" class="mt-1 block w-full" placeholder="Contoh: 112.7350000" />
                                            @error('longitude') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pencarian alamat memakai OpenStreetMap; field alamat/kota/provinsi yang kosong akan terisi otomatis.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon</label>
                                    <input wire:model="telepon" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('telepon') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                    <input wire:model="email" type="email"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('email') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nama PIC</label>
                                    <input wire:model="pic_name" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('pic_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telepon PIC</label>
                                    <input wire:model="pic_phone" type="text"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white">
                                    @error('pic_phone') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Keterangan</label>
                                    <textarea wire:model="keterangan" rows="3"
                                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-purple-500 dark:bg-gray-700 dark:text-white"></textarea>
                                    @error('keterangan') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status <span class="text-red-500">*</span></label>
                                    <x-searchable-select
                                        wire:model.live="status"
                                        :options="collect($statuses)->map(fn($s) => ['value' => $s->value, 'label' => $s->label()])->toArray()"
                                        placeholder="Pilih Status"
                                        searchPlaceholder="Cari status..."
                                        :error="$errors->has('status')"
                                    />
                                    @error('status') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                            <x-loading-button type="submit" target="save" variant="primary" size="lg"
                                loadingText="Menyimpan..." class="w-full sm:w-auto">
                                {{ $editMode ? 'Update' : 'Simpan' }}
                            </x-loading-button>
                            <x-cancel-button wire:click="closeModal" target="closeModal"
                                class="mt-3 sm:mt-0 w-full sm:w-auto" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <x-delete-modal 
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Galangan"
        message="Apakah Anda yakin ingin menghapus galangan"
        :itemName="$deletingGalanganNama"
        confirmMethod="delete"
    />
</div>
