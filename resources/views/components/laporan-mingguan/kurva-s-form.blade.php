@props([
    'hasKurvaS'          => false,
    'mingguOptions'      => [],
    'workGroupsForInput' => [],
    'minggu_ke'          => null,
    'progressPerGroup'   => [],
])

<div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
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
                <select wire:model.live="minggu_ke"
                    class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white @error('minggu_ke') border-red-500 @enderror">
                    <option value="">— Pilih minggu —</option>
                    @foreach($mingguOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected($minggu_ke == $opt['value'])>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
                @error('minggu_ke')
                    <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            {{-- Per Work Group Progress Table --}}
            @if(count($workGroupsForInput) > 0)
            <div class="overflow-x-auto"
                 x-data="{
                     pcts: @js(collect($workGroupsForInput)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)($progressPerGroup[$wg['work_group_id']] ?? 0)])->all()),
                     bobots: @js(collect($workGroupsForInput)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)$wg['bobot']])->all()),
                     kontribusi(id) { return ((this.pcts[String(id)] || 0) * (this.bobots[String(id)] || 0) / 100); },
                     totalKontribusi() { return Object.keys(this.bobots).reduce((s, id) => s + this.kontribusi(id), 0); },
                     init() { this.$wire.$watch('progressPerGroup', (v) => { for (const k in v) this.pcts[String(k)] = parseFloat(v[k]) || 0; }); }
                 }"
                 @input="const t = $event.target, id = t.dataset.wgId; if (id !== undefined) pcts[id] = parseFloat(t.value) || 0;">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-blue-100/60 dark:bg-blue-900/30">
                            <th class="px-3 py-2 text-left text-xs font-medium text-blue-700 dark:text-blue-300 uppercase">Work Group</th>
                            <th class="px-3 py-2 text-center text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-24">Bobot (%)</th>
                            <th class="px-3 py-2 text-left text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-44">Realisasi Group (%)</th>
                            <th class="px-3 py-2 text-right text-xs font-medium text-blue-700 dark:text-blue-300 uppercase w-36">Kontribusi Proyek</th>
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
                                        wire:model.blur="progressPerGroup.{{ $wg['work_group_id'] }}"
                                        data-wg-id="{{ $wg['work_group_id'] }}"
                                        class="w-full pr-6 pl-2 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                            @error('progressPerGroup.'.$wg['work_group_id']) border-red-500 @enderror"
                                        placeholder="0.00">
                                    <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                </div>
                                @error('progressPerGroup.'.$wg['work_group_id'])
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300 tabular-nums"
                                x-text="kontribusi({{ $wg['work_group_id'] }}).toFixed(2) + '%'"></td>
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
                            <td class="px-3 py-2 text-right text-xs text-blue-700 dark:text-blue-300 tabular-nums"
                                x-text="totalKontribusi().toFixed(2) + '%'"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>{{-- /overflow-x-auto (Alpine) --}}
            <p class="text-xs text-blue-500 dark:text-blue-400 mt-2">
                Masukkan % kumulatif realisasi masing-masing work group hingga periode laporan ini.
            </p>
            @endif
        @endif
    </div>
</div>
