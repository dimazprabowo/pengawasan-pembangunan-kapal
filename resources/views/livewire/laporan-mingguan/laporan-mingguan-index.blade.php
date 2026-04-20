<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Manajemen Laporan Mingguan</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola laporan mingguan pengawasan pembangunan kapal</p>
    </div>

    {{-- Jenis Kapal Filter --}}
    <div class="mb-6">
        <x-laporan.jenis-kapal-selector
            wireModel="jenisKapalId"
            :jenisKapalList="$jenisKapalList"
            variant="filter"
            placeholder="Pilih jenis kapal untuk melihat laporan"
            label="Pilih Jenis Kapal:"
            :required="false"
            :showEmptyWarning="true"
            :selectedValue="$jenisKapalId"
        />
    </div>

    {{-- Tabs --}}
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-6" aria-label="Tabs">
            <a href="{{ route('laporan-harian.index') }}" wire:navigate
                x-data="{ loading: false }" x-on:click="loading = true"
                x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 inline-flex items-center gap-2">
                <span x-show="!loading">Harian</span>
                <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memuat...
                </span>
            </a>
            <a href="{{ route('laporan-mingguan.index') }}" wire:navigate
                x-data="{ loading: false }" x-on:click="loading = true"
                x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium border-blue-500 text-blue-600 dark:text-blue-400 inline-flex items-center gap-2">
                <span x-show="!loading">Mingguan</span>
                <span x-show="loading" x-cloak class="inline-flex items-center gap-2">
                    <svg class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Memuat...
                </span>
            </a>
            <a href="#"
                class="whitespace-nowrap border-b-2 py-3 px-1 text-sm font-medium border-transparent text-gray-400 dark:text-gray-500 cursor-not-allowed opacity-50"
                title="Segera hadir">
                Bulanan
            </a>
        </nav>
    </div>

    {{-- Search & Actions --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text"
                placeholder="Cari laporan mingguan..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <div class="w-full lg:w-32">
            <x-searchable-select
                wire:model.live="perPage"
                :options="[
                    ['value' => '10', 'label' => '10'],
                    ['value' => '25', 'label' => '25'],
                    ['value' => '50', 'label' => '50'],
                    ['value' => '100', 'label' => '100']
                ]"
                placeholder="10"
                searchPlaceholder="Pilih jumlah..."
                :clearable="false"
            />
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            @can('exportExcel', \App\Models\LaporanMingguan::class)
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('exportPdf', \App\Models\LaporanMingguan::class)
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
            @can('create', \App\Models\LaporanMingguan::class)
                <a href="{{ route('laporan-mingguan.create') }}" wire:navigate
                    x-data="{ loading: false }" x-on:click="loading = true"
                    x-bind:class="loading ? 'opacity-75 pointer-events-none' : ''"
                    class="inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500 px-3 py-2 text-sm gap-1.5 flex-1 lg:flex-none whitespace-nowrap">
                    <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-show="!loading">Tambah Laporan</span>
                    <span x-show="loading" x-cloak>Memuat...</span>
                </a>
            @endcan
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">No</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Judul</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tanggal Laporan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pembuat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Dibuat</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($laporanList as $index => $laporan)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $laporanList->firstItem() + $index }}
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $laporan->judul }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900 dark:text-white">{{ $laporan->tanggal_laporan->translatedFormat('d M Y') }}</div>
                            </td>
                            <td class="px-6 py-4">
                                @if($laporan->jenisKapal)
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $laporan->jenisKapal->nama }}</div>
                                    @if($laporan->jenisKapal->company)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">Perusahaan:</span> {{ $laporan->jenisKapal->company->name }}
                                        </div>
                                    @endif
                                    @if($laporan->jenisKapal->galangan)
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            <span class="font-medium">Galangan:</span> {{ $laporan->jenisKapal->galangan->nama }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ substr($laporan->user->name ?? '-', 0, 1) }}
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $laporan->user->name ?? '-' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $laporan->created_at->translatedFormat('d M Y H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex items-center justify-end gap-2">
                                    @can('view', $laporan)
                                        <a href="{{ route('laporan-mingguan.show', $laporan) }}" wire:navigate
                                            x-data="{ loading: false }" x-on:click="loading = true"
                                            x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                                            class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
                                            title="Detail">
                                            <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </a>
                                    @endcan
                                    @can('update', $laporan)
                                        <a href="{{ route('laporan-mingguan.edit', $laporan) }}" wire:navigate
                                            x-data="{ loading: false }" x-on:click="loading = true"
                                            x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                                            class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                            title="Edit">
                                            <svg x-show="!loading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                            <svg x-show="loading" x-cloak class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </a>
                                    @endcan
                                    @can('delete', $laporan)
                                        <button wire:click="confirmDelete({{ $laporan->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="confirmDelete({{ $laporan->id }})"
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50"
                                            title="Hapus">
                                            <svg wire:loading.class="hidden" wire:target="confirmDelete({{ $laporan->id }})" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                            <svg wire:loading wire:target="confirmDelete({{ $laporan->id }})" class="animate-spin w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada laporan mingguan</p>
                                @can('create', \App\Models\LaporanMingguan::class)
                                    <a href="{{ route('laporan-mingguan.create') }}" wire:navigate
                                        x-data="{ loading: false }" x-on:click="loading = true"
                                        x-bind:class="loading ? 'opacity-50 pointer-events-none' : ''"
                                        class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300">
                                        <svg x-show="!loading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                        <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        Tambah Laporan Pertama
                                    </a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($laporanList->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $laporanList->links() }}
            </div>
        @endif
    </div>

    {{-- Delete Confirmation Modal --}}
    <x-delete-modal
        :show="$showDeleteModal"
        wire:model="showDeleteModal"
        title="Hapus Laporan"
        message="Apakah Anda yakin ingin menghapus laporan"
        :itemName="$deletingLaporanJudul"
        confirmMethod="delete"
    />
</div>
