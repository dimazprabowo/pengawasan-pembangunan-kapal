<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Manajemen Laporan</h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pilih jenis kapal untuk melihat dan mengelola laporan pengawasan pembangunan</p>
    </div>

    {{-- Search + Filter + Actions --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center gap-3">
        <div class="flex-1">
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari jenis kapal..."
                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        </div>

        <x-filter-popover :filters="['companyFilter', 'galanganFilter']" :per-page="true">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Perusahaan</label>
                <x-searchable-select
                    wire:model.live="companyFilter"
                    :options="collect($companies)->map(fn($c) => ['value' => $c->id, 'label' => $c->name])->toArray()"
                    placeholder="Semua Perusahaan"
                    searchPlaceholder="Cari perusahaan..."
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Galangan</label>
                <x-searchable-select
                    wire:model.live="galanganFilter"
                    :options="collect($galangans)->map(fn($g) => ['value' => $g->id, 'label' => $g->nama])->toArray()"
                    placeholder="Semua Galangan"
                    searchPlaceholder="Cari galangan..."
                />
            </div>
        </x-filter-popover>

        <div class="flex items-center gap-2 flex-wrap">
            @can('exportExcel', \App\Models\LaporanHarian::class)
                <x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </x-slot:icon>
                    Excel
                </x-loading-button>
            @endcan
            @can('exportPdf', \App\Models\LaporanHarian::class)
                <x-loading-button wire:click="exportPdf" target="exportPdf" variant="danger" size="md" loadingText="Exporting..." title="Export PDF">
                    <x-slot:icon>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    </x-slot:icon>
                    PDF
                </x-loading-button>
            @endcan
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Jenis Kapal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Perusahaan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Galangan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laporan</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Progress</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Laporan Terakhir</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Aksi</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($jenisKapalList as $jenisKapal)
                        <tr wire:key="kapal-{{ $jenisKapal->id }}" class="hover:bg-blue-50 dark:hover:bg-blue-900/10 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-xs">
                                            {{ strtoupper(substr($jenisKapal->nama, 0, 2)) }}
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $jenisKapal->nama }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->company)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $jenisKapal->company->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $jenisKapal->company->code }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->galangan)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ $jenisKapal->galangan->nama }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $jenisKapal->galangan->kode }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Harian:</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400">
                                            {{ $jenisKapal->laporan_harian_count }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 w-16">Mingguan:</span>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                            {{ $jenisKapal->laporan_mingguan_count }}
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->progress_aktual !== null)
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-blue-600 h-2 rounded-full" style="width: {{ min(100, $jenisKapal->progress_aktual) }}%"></div>
                                        </div>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $jenisKapal->progress_aktual }}%</span>
                                    </div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($jenisKapal->laporan_harian_max_tanggal_laporan)
                                    <div class="text-sm text-gray-900 dark:text-white">{{ \Carbon\Carbon::parse($jenisKapal->laporan_harian_max_tanggal_laporan)->translatedFormat('d M Y') }}</div>
                                @else
                                    <span class="text-sm text-gray-400 dark:text-gray-500">Belum ada</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <x-loading-button wire:click="openLaporan({{ $jenisKapal->id }})"
                                    target="openLaporan({{ $jenisKapal->id }})"
                                    variant="primary" size="sm" loadingText="Memuat..."
                                    title="Buka laporan {{ $jenisKapal->nama }}"
                                    class="w-24"
                                    wire:key="btn-open-{{ $jenisKapal->id }}">
                                    <x-slot:icon>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </x-slot:icon>
                                    Buka
                                </x-loading-button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                @if($search || $companyFilter || $galanganFilter)
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Tidak ada jenis kapal yang cocok dengan filter</p>
                                @else
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada jenis kapal yang dapat diakses</p>
                                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Hubungi administrator untuk mendapatkan akses ke data jenis kapal</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($jenisKapalList->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $jenisKapalList->links() }}
            </div>
        @endif
    </div>
</div>
