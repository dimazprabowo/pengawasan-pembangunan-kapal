@props([
    'hasKurvaS'          => false,
    'mingguOptions'      => [],
    'workGroupsForInput' => [],
    'minggu_ke'          => null,
    'progressPerGroup'   => [],
    'progressHistory'    => [],
])

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2 rounded-t-lg">
        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Kurva S Progress</h3>
        <span class="ml-auto text-xs text-gray-400 dark:text-gray-500 font-normal">Opsional</span>
    </div>

    <div class="p-5">
        @if(!$hasKurvaS)
            <div class="flex items-start gap-2.5 p-3.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <p class="text-xs text-amber-700 dark:text-amber-300">
                    Kurva S belum dikonfigurasi untuk jenis kapal ini.
                    Atur jadwal Kurva S terlebih dahulu di menu <strong>Master Data → Jenis Kapal</strong>.
                </p>
            </div>
        @else
            {{-- Minggu ke- --}}
            <div class="mb-4 max-w-xs">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">
                    Minggu Ke- <span class="text-gray-400">(opsional)</span>
                </label>
                <x-searchable-select
                    wire:model.live="minggu_ke"
                    :options="$mingguOptions"
                    placeholder="— Pilih minggu —"
                    searchPlaceholder="Cari minggu..."
                    :clearable="true"
                    :error="$errors->has('minggu_ke')"
                    class="text-sm"
                />
                @error('minggu_ke')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            {{-- Per Work Group Progress Table --}}
            @if(count($workGroupsForInput) > 0)
            <div class="overflow-x-auto">

                {{-- Warning banner when kontribusi exceeds bobot --}}
                @if($this->anyExceedsBobot)
                <div class="mb-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-3 flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">Kontribusi Proyek melebihi Bobot</p>
                        <p class="text-xs text-red-600 dark:text-red-300 mt-0.5">Kontribusi proyek tidak boleh melebihi bobot work group. Silakan kurangi nilai realisasi group.</p>
                    </div>
                </div>
                @endif

                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-blue-100/60 dark:bg-blue-900/30">
                            <th class="px-3 py-2 text-left text-xs font-medium text-blue-700 dark:text-blue-300 uppercase">Work Group</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-24">Bobot (%)</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-44">Realisasi Group (%)</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-36">Kontribusi Proyek</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-36">Total Kontribusi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-blue-100 dark:divide-blue-900/30">
                        @foreach($workGroupsForInput as $wg)
                        <tr class="hover:bg-blue-50/50 dark:hover:bg-blue-900/10">
                            <td class="px-3 py-2 font-medium text-gray-800 dark:text-gray-200">{{ $wg['nama'] }}</td>
                            <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-400">{{ number_format($wg['bobot'], 2) }}</td>
                            <td class="px-3 py-2">
                                <div class="relative">
                                    <input type="number" step="0.01" min="0" max="100"
                                        wire:model.live="progressPerGroup.{{ $wg['work_group_id'] }}"
                                        class="w-full pr-6 pl-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                            @error('progressPerGroup.'.$wg['work_group_id']) border-red-500 @enderror"
                                        placeholder="0.00">
                                    <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                </div>
                                @error('progressPerGroup.'.$wg['work_group_id'])
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums
                                @php
                                    $totalKontribusiHistory = $this->totalKontribusiHistory;
                                    $kontribusi = $this->kontribusiPerGroup[$wg['work_group_id']] ?? 0;
                                    $totalKontribusi = $totalKontribusiHistory[$wg['work_group_id']] ?? 0;
                                    $exceedsBobot = $totalKontribusi > $wg['bobot'];
                                @endphp
                                {{ $exceedsBobot ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300' }}">
                                <span wire:loading.remove wire:target="progressPerGroup.{{ $wg['work_group_id'] }}">
                                    {{ number_format($kontribusi, 2) }}%
                                </span>
                                <svg wire:loading wire:target="progressPerGroup.{{ $wg['work_group_id'] }}" class="animate-spin w-4 h-4 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium
                                {{ $exceedsBobot ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-700 dark:text-gray-300' }}">
                                <span wire:loading.remove wire:target="progressPerGroup.{{ $wg['work_group_id'] }}">
                                    {{ number_format($totalKontribusi, 2) }}%
                                </span>
                                <svg wire:loading wire:target="progressPerGroup.{{ $wg['work_group_id'] }}" class="animate-spin w-4 h-4 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-blue-100/60 dark:bg-blue-900/30 font-semibold">
                            <td class="px-3 py-2 text-xs text-blue-700 dark:text-blue-300 uppercase">Total</td>
                            <td class="px-3 py-2 text-center text-xs text-blue-700 dark:text-blue-300">
                                {{ number_format(collect($workGroupsForInput)->sum('bobot'), 2) }}%
                            </td>
                            <td class="px-3 py-2"></td>
                            <td class="px-3 py-2 text-right text-xs text-blue-700 dark:text-blue-300 tabular-nums">
                                <span wire:loading.remove wire:target="progressPerGroup">
                                    {{ number_format($this->totalKontribusi, 2) }}%
                                </span>
                                <svg wire:loading wire:target="progressPerGroup" class="animate-spin w-4 h-4 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                            <td class="px-3 py-2 text-right text-xs text-blue-700 dark:text-blue-300 tabular-nums">
                                <span wire:loading.remove wire:target="progressPerGroup">
                                    {{ number_format($this->totalAllKontribusiHistory, 2) }}%
                                </span>
                                <svg wire:loading wire:target="progressPerGroup" class="animate-spin w-4 h-4 text-blue-500 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <p class="text-xs text-blue-500 dark:text-blue-400 mt-2">
                Masukkan % kumulatif realisasi masing-masing work group hingga periode laporan ini.
            </p>
            @endif
        @endif
    </div>
</div>
