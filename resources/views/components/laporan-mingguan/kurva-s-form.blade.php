@props([
    'hasKurvaS'          => false,
    'mingguOptions'      => [],
    'workGroupsForInput' => [],
    'minggu_ke'          => null,
    'progressPerGroup'   => [],
    'progressHistory'    => [],
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
                     currentMingguKe: {{ $minggu_ke ?? 0 }},
                     progressHistory: @js($progressHistory),
                     historyTotals: @js(collect($workGroupsForInput)->mapWithKeys(function($wg) use ($progressHistory, $minggu_ke) {
                         $total = 0;
                         foreach($progressHistory as $hist) {
                             // Exclude current week from history (only sum previous weeks)
                             if(isset($hist['progress'][$wg['work_group_id']]) && $hist['minggu_ke'] != $minggu_ke) {
                                 $total += $hist['progress'][$wg['work_group_id']] * $wg['bobot'] / 100;
                             }
                         }
                         return [(string)$wg['work_group_id'] => $total];
                     })->all()),
                     kontribusi(id) { const k = this.kontribusiVal(id); return k; },
                     kontribusiVal(id) { return ((this.pcts[String(id)] || 0) * (this.bobots[String(id)] || 0) / 100); },
                     historyKontribusi(wgId, weekIndex) { const p = this.progressHistory[weekIndex]?.progress[String(wgId)]; return p ? p * this.bobots[String(wgId)] / 100 : 0; },
                     totalKontribusiHistory(id) { return ((this.historyTotals && this.historyTotals[String(id)]) || 0) + this.kontribusiVal(id); },
                     totalAllKontribusiHistory() { return Object.keys(this.bobots).reduce((s, id) => s + this.totalKontribusiHistory(id), 0); },
                     exceedsBobot(id) { return this.kontribusiVal(id) > this.bobots[String(id)]; },
                     historyExceedsBobot(id) { return this.totalKontribusiHistory(id) > this.bobots[String(id)]; },
                     anyExceedsBobot() { return Object.keys(this.bobots).some(id => this.exceedsBobot(id)); },
                     totalKontribusi() { return Object.keys(this.bobots).reduce((s, id) => s + this.kontribusiVal(id), 0); },
                     totalHistoryKontribusi(wgId) { return this.progressHistory.reduce((s, h, i) => s + this.historyKontribusi(wgId, i), 0); },
                     init() { this.$wire.$watch('progressPerGroup', (v) => { for (const k in v) this.pcts[String(k)] = parseFloat(v[k]) || 0; }); }
                 }"
                 @input="const t = $event.target, id = t.dataset.wgId; if (id !== undefined) { pcts[id] = parseFloat(t.value) || 0; window.dispatchEvent(new CustomEvent('progress-input-changed', { detail: { pcts } })); }">

                {{-- Warning banner when kontribusi exceeds bobot --}}
                <div x-show="anyExceedsBobot()" x-cloak
                     class="mb-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-3 flex items-start gap-2">
                    <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-red-700 dark:text-red-400">Kontribusi Proyek melebihi Bobot</p>
                        <p class="text-xs text-red-600 dark:text-red-300 mt-0.5">Kontribusi proyek tidak boleh melebihi bobot work group. Silakan kurangi nilai realisasi group.</p>
                    </div>
                </div>

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
                            <td class="px-3 py-2 text-right tabular-nums"
                                :class="exceedsBobot({{ $wg['work_group_id'] }}) ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-600 dark:text-gray-300'"
                                x-text="kontribusi({{ $wg['work_group_id'] }}).toFixed(2) + '%'"></td>
                            <td class="px-3 py-2 text-right tabular-nums font-medium"
                                :class="historyExceedsBobot({{ $wg['work_group_id'] }}) ? 'text-red-600 dark:text-red-400 font-bold' : 'text-gray-700 dark:text-gray-300'"
                                x-text="totalKontribusiHistory({{ $wg['work_group_id'] }}).toFixed(2) + '%'"></td>
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
                            <td class="px-3 py-2 text-right text-xs text-blue-700 dark:text-blue-300 tabular-nums"
                                x-text="totalAllKontribusiHistory().toFixed(2) + '%'"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>{{-- /overflow-x-auto (Alpine) }}

            {{-- Progress History Table --}}
            @if(count($progressHistory) > 0)
            <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4"
                 x-data="{
                     bobots: @js(collect($workGroupsForInput)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)$wg['bobot']])->all()),
                     progressHistory: @js($progressHistory),
                     currentMingguKe: {{ $minggu_ke ?? 0 }},
                     pcts: @js(collect($workGroupsForInput)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)($progressPerGroup[$wg['work_group_id']] ?? 0)])->all()),
                     allHistory() {
                         const all = [...this.progressHistory];
                         const currentWeekIndex = all.findIndex(h => h.minggu_ke === this.currentMingguKe);
                         const currentProgress = {};
                         Object.keys(this.pcts).forEach(id => {
                             currentProgress[id] = this.pcts[id];
                         });
                         const currentEntry = {
                             minggu_ke: this.currentMingguKe,
                             progress: currentProgress,
                             created_at: new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
                         };
                         if (currentWeekIndex >= 0) {
                             all[currentWeekIndex] = currentEntry;
                         } else {
                             all.push(currentEntry);
                         }
                         return all.sort((a, b) => a.minggu_ke - b.minggu_ke);
                     },
                     historyKontribusi(wgId, weekIndex) {
                         const hist = this.allHistory()[weekIndex];
                         if (!hist || !hist.progress) return 0;
                         const p = hist.progress[String(wgId)];
                         return p ? p * this.bobots[String(wgId)] / 100 : 0;
                     },
                     totalHistoryKontribusi(wgId) { return this.allHistory().reduce((s, h, i) => s + this.historyKontribusi(wgId, i), 0); },
                     init() {
                        window.addEventListener('progress-input-changed', (e) => {
                            if (e.detail && e.detail.pcts) {
                                for (const k in e.detail.pcts) {
                                    this.pcts[String(k)] = parseFloat(e.detail.pcts[k]) || 0;
                                }
                            }
                        });
                        this.$watch('$wire.progressPerGroup', (v) => {
                            for (const k in v) this.pcts[String(k)] = parseFloat(v[k]) || 0;
                        });
                     }
                 }">
                <h4 class="text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider mb-3">Riwayat Progress per Work Group</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900">
                                <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Minggu</th>
                                @foreach($workGroupsForInput as $wg)
                                <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400">{{ $wg['nama'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="(hist, weekIndex) in allHistory()" :key="weekIndex">
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-400">
                                    <span class="font-medium" x-text="'Minggu ' + hist.minggu_ke"></span>
                                    <span class="text-gray-400 ml-1" x-text="'(' + hist.created_at + ')'"></span>
                                </td>
                                <template x-for="wgId in Object.keys(bobots)" :key="wgId">
                                <td class="px-3 py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums"
                                    x-text="historyKontribusi(wgId, weekIndex).toFixed(2) + '%'"></td>
                                </template>
                            </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100 dark:bg-gray-800 font-semibold">
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">Total Kontribusi (Semua Minggu)</td>
                                <template x-for="wgId in Object.keys(bobots)" :key="wgId">
                                <td class="px-3 py-2 text-center text-gray-700 dark:text-gray-300 tabular-nums"
                                    x-text="totalHistoryKontribusi(wgId).toFixed(2) + '%'"></td>
                                </template>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

            <p class="text-xs text-blue-500 dark:text-blue-400 mt-2">
                Masukkan % kumulatif realisasi masing-masing work group hingga periode laporan ini.
            </p>
            @endif
        @endif
    </div>
</div>
