{{-- Partial: Statistik Kurva-S — tabel progres per kapal + panel grafik --}}
<div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

    {{-- Tabel progres per kapal --}}
    <div class="xl:col-span-7 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Progres Kurva-S per Kapal</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Deviasi terburuk ditampilkan paling atas. Klik baris untuk melihat grafik.</p>
            </div>
            <span class="inline-block shrink-0 rounded-full text-center bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1 text-xs font-semibold text-blue-700 dark:text-blue-300">{{ $kurvaSKapal->count() }} kapal</span>
        </div>

        <div class="flex-1 overflow-x-auto custom-scrollbar">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Kapal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Progress</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Deviasi</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                        <th class="px-4 py-3 w-px"><span class="sr-only">Pilih</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($kurvaSKapal as $kapal)
                        @php
                            $isSelected = $selectedKurvaSkapal?->id === $kapal->id;
                            $selectAction = "selectKurvaSkapal('".($isSelected ? '' : $kapal->getRouteKey())."')";
                        @endphp
                        <tr wire:key="kurvas-{{ $kapal->id }}" wire:click="{{ $selectAction }}"
                            class="cursor-pointer transition-colors {{ $isSelected ? 'bg-blue-50/70 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/30' }}">
                            <td class="px-5 py-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white truncate max-w-52">{{ $kapal->nama }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-52">
                                    {{ $kapal->owner?->name ?? 'Tanpa owner' }} · {{ $kapal->galangan?->nama ?? 'Tanpa galangan' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 min-w-44">
                                <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400 mb-1">
                                    <span>Aktual <strong class="text-gray-800 dark:text-gray-100">{{ $kapal->progress_aktual !== null ? number_format($kapal->progress_aktual, 1).'%' : '—' }}</strong></span>
                                    <span>Rencana {{ $kapal->progress_rencana !== null ? number_format($kapal->progress_rencana, 1).'%' : '—' }}</span>
                                </div>
                                <div class="relative h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                                    <div class="h-full rounded-full {{ $kapal->progress_status->barClass() }}" style="width: {{ min(100, max(0, $kapal->progress_aktual ?? 0)) }}%"></div>
                                    @if ($kapal->progress_rencana !== null)
                                        <div class="absolute top-0 h-full w-0.5 bg-gray-800/70 dark:bg-white/70" style="left: {{ min(100, max(0, $kapal->progress_rencana)) }}%" title="Rencana"></div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-sm font-semibold whitespace-nowrap
                                {{ $kapal->progress_deviasi === null ? 'text-gray-400 dark:text-gray-500' : ($kapal->progress_deviasi >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400') }}">
                                {{ $kapal->progress_deviasi === null ? '—' : ($kapal->progress_deviasi >= 0 ? '+' : '').number_format($kapal->progress_deviasi, 2).'%' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap {{ $kapal->progress_status->badgeClass() }}">{{ $kapal->progress_status->label() }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <svg wire:loading.class="hidden" wire:target="{{ $selectAction }}" class="w-4 h-4 inline-block {{ $isSelected ? 'text-blue-500' : 'text-gray-300 dark:text-gray-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <svg wire:loading wire:target="{{ $selectAction }}" class="animate-spin w-4 h-4 inline-block text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-12 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Belum ada kapal dengan jadwal Kurva-S.</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500">Atur work group melalui Master Data &rsaquo; Data Proyek.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Panel grafik kapal terpilih --}}
    <div class="xl:col-span-5">
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden h-full flex flex-col">
            <div wire:loading.flex wire:target="selectKurvaSkapal,setKurvaSMode"
                 class="absolute inset-0 z-10 items-center justify-center rounded-xl bg-white/80 dark:bg-gray-800/80">
                <svg class="animate-spin w-6 h-6 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </div>

            @if ($selectedKurvaSkapal && $kurvaSChart)
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">{{ $selectedKurvaSkapal->nama }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $selectedKurvaSkapal->galangan?->nama ?? 'Tanpa galangan' }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 overflow-hidden text-xs font-medium">
                                <button type="button" wire:click="setKurvaSMode('mingguan')"
                                    class="px-3 py-1.5 transition-colors {{ $kurvaSMode === 'mingguan' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">Mingguan</button>
                                <button type="button" wire:click="setKurvaSMode('bulanan')"
                                    class="px-3 py-1.5 transition-colors {{ $kurvaSMode === 'bulanan' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600' }}">Bulanan</button>
                            </div>
                            <button type="button" wire:click="selectKurvaSkapal(null)" title="Tutup grafik" aria-label="Tutup grafik"
                                class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="p-5 flex-1">
                    <x-kurva-s-chart :chartData="$kurvaSChart" :data-kapal-nama="$selectedKurvaSkapal->nama"
                        chart-id="dashboard-kurva-s" height="300px" />
                </div>
            @else
                <div class="flex-1 flex flex-col items-center justify-center py-16 px-6 text-center">
                    <svg class="w-16 h-16 text-blue-200 dark:text-blue-900/50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                    <p class="mt-3 text-sm font-medium text-gray-700 dark:text-gray-200">Pilih kapal untuk melihat grafik</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 max-w-xs">Klik salah satu baris pada tabel untuk menampilkan kurva rencana vs realisasi kapal tersebut.</p>
                </div>
            @endif
        </div>
    </div>
</div>
