@props([
    'workGroups' => [],
    'progressHistory' => [],
    'fullProgressHistory' => null, // Pre-calculated full history from server (includes current week)
])

@if(count($progressHistory) > 0 || ($fullProgressHistory && count($fullProgressHistory) > 0))
<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-clip']) }}>
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Progress per Work Group</h3>
    </div>
    <div class="p-5"
         @resize.window="updateNavH()"
         @scroll.window.passive="updateNavH()"
         x-data="{
             bobots: @js(collect($workGroups)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)$wg['bobot']])->all()),
             progressHistory: @js($fullProgressHistory ?? $progressHistory),
             navH: 0,
             init() {
                 @isset($fullProgressHistory)
                 this.$watch(
                     () => this.$wire.fullProgressHistory,
                     (val) => { if (Array.isArray(val)) this.progressHistory = val; }
                 );
                 @endisset
                 this.updateNavH();
                 this.$watch('progressHistory', () => this.$nextTick(() => this.syncScroll()));
             },
             tableWidth() {
                 return 436 + (this.progressHistory.length * 104);
             },
             updateNavH() {
                 const nav = document.querySelector('nav.sticky');
                 this.navH = nav ? Math.max(0, Math.round(nav.getBoundingClientRect().bottom)) : 0;
             },
             syncScroll() {
                 this.$refs.headScroll.scrollLeft = this.$refs.bodyScroll.scrollLeft;
             },
             forwardWheel(e) {
                 const dx = e.deltaX || (e.shiftKey ? e.deltaY : 0);
                 if (!dx) return;
                 e.preventDefault();
                 this.$refs.bodyScroll.scrollLeft += dx;
             },
             historyKontribusi(wgId, weekIndex) {
                 const hist = this.progressHistory[weekIndex];
                 if (!hist || !hist.progress) return 0;
                 const p = hist.progress[String(wgId)];
                 // TIDAK dibulatkan, return nilai mentah untuk dijumlahkan
                 return p ? p * this.bobots[String(wgId)] / 100 : 0;
             },
             historyPlanKontribusi(wgId, weekIndex) {
                const hist = this.progressHistory[weekIndex];
                 if (!hist || !hist.plans) return 0;
                 const plan = hist.plans[String(wgId)];
                 // TIDAK dibulatkan, return nilai mentah untuk dijumlahkan
                 return plan ? plan * this.bobots[String(wgId)] / 100 : 0;
            },
            historyDeviation(wgId, weekIndex) {
                return Math.round((this.historyKontribusi(wgId, weekIndex) - this.historyPlanKontribusi(wgId, weekIndex)) * 100) / 100;
            },
            totalHistoryDeviation(wgId) {
                return Math.round((this.totalHistoryKontribusi(wgId) - this.totalHistoryPlan(wgId)) * 100) / 100;
            },
            totalHistoryPlan(wgId) {
                return Math.round(this.progressHistory.reduce((s, h, i) => s + this.historyPlanKontribusi(wgId, i), 0) * 100) / 100;
            },
             totalHistoryKontribusi(wgId) { return Math.round(this.progressHistory.reduce((s, h, i) => s + this.historyKontribusi(wgId, i), 0) * 100) / 100; },
             totalWeekPlan(weekIndex) {
                 // TIDAK dibulatkan per minggu — pembulatan sekali di totalAllWeeks* agar konsisten dengan cumulative_* backend
                 return Object.keys(this.bobots).reduce((s, wgId) => s + this.historyPlanKontribusi(wgId, weekIndex), 0);
             },
             totalWeekActual(weekIndex) {
                 return Object.keys(this.bobots).reduce((s, wgId) => s + this.historyKontribusi(wgId, weekIndex), 0);
             },
             totalAllWeeksPlan() {
                 return Math.round(this.progressHistory.reduce((s, h, i) => s + this.totalWeekPlan(i), 0) * 100) / 100;
             },
             totalAllWeeksActual() {
                 return Math.round(this.progressHistory.reduce((s, h, i) => s + this.totalWeekActual(i), 0) * 100) / 100;
             },
             totalAllWeeksDeviation() {
                return Math.round((this.totalAllWeeksActual() - this.totalAllWeeksPlan()) * 100) / 100;
            },
            totalBobot() {
                return Math.round(Object.values(this.bobots).reduce((s, b) => s + b, 0) * 100) / 100;
            }
         }"
         @progress-history-updated.window="progressHistory = $event.detail.history">
        {{-- Freeze panes ala Excel: header & body = 2 tabel dengan colgroup identik; header sticky di bawah navbar, scroll horizontal disinkronkan dari body --}}
        <div class="sticky z-30 bg-slate-100 dark:bg-slate-900 shadow-sm" :style="'top: ' + navH + 'px'">
            <div x-ref="headScroll" class="overflow-hidden" @wheel="forwardWheel($event)">
                <table class="w-full table-fixed text-xs border-separate border-spacing-0" :style="'min-width: ' + tableWidth() + 'px'">
                    <colgroup>
                        <col style="width: 40px"><col style="width: 160px"><col style="width: 64px"><col style="width: 84px">
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'hcol-' + weekIndex"><col style="width: 104px"></template>
                        <col style="width: 88px">
                    </colgroup>
                <thead>
                    <tr>
                        <th class="sticky left-0 z-20 bg-slate-200 dark:bg-slate-950 px-2 py-2 text-center font-semibold text-blue-800 dark:text-blue-300 w-[40px] min-w-[40px] border-r border-b-2 border-r-slate-300 border-b-blue-600 dark:border-r-slate-700 dark:border-b-blue-500">No</th>
                        <th class="sticky left-[40px] z-20 bg-slate-200 dark:bg-slate-950 px-3 py-2 text-center font-semibold text-blue-800 dark:text-blue-300 w-[160px] min-w-[160px] max-w-[160px] border-r border-b-2 border-r-slate-300 border-b-blue-600 dark:border-r-slate-700 dark:border-b-blue-500">Work Group</th>
                        <th class="sticky left-[200px] z-20 bg-slate-200 dark:bg-slate-950 px-2 py-2 text-center font-semibold text-blue-800 dark:text-blue-300 w-[64px] min-w-[64px] border-r border-b-2 border-r-slate-300 border-b-blue-600 dark:border-r-slate-700 dark:border-b-blue-500">Bobot</th>
                        <th class="sticky left-[264px] z-20 bg-slate-200 dark:bg-slate-950 px-2 py-0.5 text-center align-middle font-semibold text-blue-800 dark:text-blue-300 w-[84px] min-w-[84px] border-r border-b-2 border-r-slate-300 border-b-blue-600 dark:border-r-slate-700 dark:border-b-blue-500">Physical<br>Progress</th>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'head-' + weekIndex">
                        <th class="bg-slate-100 dark:bg-slate-900 px-3 py-2 text-center font-semibold text-blue-800 dark:text-blue-300 min-w-[104px] border-r border-b-2 border-r-slate-200 border-b-blue-600 dark:border-r-slate-800 dark:border-b-blue-500">
                            <div x-text="'Minggu ' + hist.minggu_ke"></div>
                            <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400 whitespace-nowrap leading-tight">
                                <div x-text="hist.periode_mulai || hist.created_at"></div>
                                <div x-show="hist.periode_selesai" x-text="'s/d ' + (hist.periode_selesai || '')"></div>
                            </div>
                        </th>
                        </template>
                        <th class="sticky right-0 z-20 bg-slate-200 dark:bg-slate-950 px-3 py-2 text-center font-semibold text-blue-800 dark:text-blue-300 w-[88px] min-w-[88px] border-l border-b-2 border-l-slate-300 border-b-blue-600 dark:border-l-slate-700 dark:border-b-blue-500">Total</th>
                    </tr>
                </thead>
                </table>
            </div>
        </div>
        <div x-ref="bodyScroll" class="overflow-x-auto custom-scrollbar" @scroll.passive="syncScroll()">
            <table class="w-full table-fixed text-xs border-separate border-spacing-0" :style="'min-width: ' + tableWidth() + 'px'">
                <colgroup>
                    <col style="width: 40px"><col style="width: 160px"><col style="width: 64px"><col style="width: 84px">
                    <template x-for="(hist, weekIndex) in progressHistory" :key="'bcol-' + weekIndex"><col style="width: 104px"></template>
                    <col style="width: 88px">
                </colgroup>
                <tbody>
                    @foreach($workGroups as $wg)
                    @php $groupBorder = $loop->first ? '' : 'border-t-2 border-gray-300 dark:border-gray-600'; @endphp
                    {{-- Rencana --}}
                    <tr>
                        <td rowspan="3" class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[40px] min-w-[40px] align-middle text-center tabular-nums font-medium text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 {{ $groupBorder }}">{{ $loop->iteration }}</td>
                        <td rowspan="3" class="sticky left-[40px] z-10 bg-gray-50 dark:bg-gray-900 px-3 py-2 w-[160px] min-w-[160px] align-middle font-medium text-gray-700 dark:text-gray-300 max-w-[160px] border-r border-gray-200 dark:border-gray-700 {{ $groupBorder }}">
                            {{ $wg['nama'] }}
                        </td>
                        <td rowspan="3" class="sticky left-[200px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[64px] min-w-[64px] align-middle text-center tabular-nums font-medium text-gray-600 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 {{ $groupBorder }}">
                            {{ rtrim(rtrim(number_format((float) $wg['bobot'], 2), '0'), '.') }}%
                        </td>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700 {{ $groupBorder }}">Rencana</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'p-{{ $wg['work_group_id'] }}-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums text-gray-500 dark:text-gray-400 {{ $groupBorder }}" x-text="historyPlanKontribusi('{{ $wg['work_group_id'] }}', weekIndex).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 text-center tabular-nums font-medium text-gray-500 dark:text-gray-400 border-l border-gray-200 dark:border-gray-700 {{ $groupBorder }}" x-text="totalHistoryPlan('{{ $wg['work_group_id'] }}').toFixed(2) + '%'"></td>
                    </tr>
                    {{-- Aktual --}}
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left text-gray-700 dark:text-gray-300 border-r border-gray-200 dark:border-gray-700">Aktual</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'a-{{ $wg['work_group_id'] }}-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-medium text-gray-700 dark:text-gray-300" x-text="historyKontribusi('{{ $wg['work_group_id'] }}', weekIndex).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 text-center tabular-nums font-medium text-gray-700 dark:text-gray-300 border-l border-gray-200 dark:border-gray-700" x-text="totalHistoryKontribusi('{{ $wg['work_group_id'] }}').toFixed(2) + '%'"></td>
                    </tr>
                    {{-- Deviasi --}}
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left text-gray-500 dark:text-gray-400 border-r border-gray-200 dark:border-gray-700">Deviasi</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'d-{{ $wg['work_group_id'] }}-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-medium"
                            :class="historyDeviation('{{ $wg['work_group_id'] }}', weekIndex) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="(historyDeviation('{{ $wg['work_group_id'] }}', weekIndex) >= 0 ? '+' : '') + historyDeviation('{{ $wg['work_group_id'] }}', weekIndex).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-50 dark:bg-gray-800 px-2 py-0.5 text-center tabular-nums font-medium border-l border-gray-200 dark:border-gray-700"
                            :class="totalHistoryDeviation('{{ $wg['work_group_id'] }}') >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="(totalHistoryDeviation('{{ $wg['work_group_id'] }}') >= 0 ? '+' : '') + totalHistoryDeviation('{{ $wg['work_group_id'] }}').toFixed(2) + '%'"></td>
                    </tr>
                    @endforeach
                    {{-- Per Minggu --}}
                    <tr>
                        <td rowspan="3" colspan="2" class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-900 px-3 py-2 align-middle font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600">Per Minggu</td>
                        <td rowspan="3" class="sticky left-[200px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[64px] min-w-[64px] align-middle text-center tabular-nums font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600" x-text="totalBobot().toFixed(2) + '%'"></td>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600">Rencana</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'wp-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-t-2 border-gray-300 dark:border-gray-600" x-text="(hist.week_plan || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-l border-t-2 border-l-gray-200 border-t-gray-300 dark:border-l-gray-700 dark:border-t-gray-600" x-text="totalAllWeeksPlan().toFixed(2) + '%'"></td>
                    </tr>
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">Aktual</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'wa-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white" x-text="(hist.week_actual || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700" x-text="totalAllWeeksActual().toFixed(2) + '%'"></td>
                    </tr>
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">Deviasi</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'wd-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold"
                            :class="(hist.week_deviation || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((hist.week_deviation || 0) >= 0 ? '+' : '') + (hist.week_deviation || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold border-l border-gray-200 dark:border-gray-700"
                            :class="totalAllWeeksDeviation() >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="(totalAllWeeksDeviation() >= 0 ? '+' : '') + totalAllWeeksDeviation().toFixed(2) + '%'"></td>
                    </tr>
                    {{-- Total Kumulatif --}}
                    <tr>
                        <td rowspan="3" colspan="2" class="sticky left-0 z-10 bg-gray-50 dark:bg-gray-900 px-3 py-2 align-middle font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600">Total Kumulatif</td>
                        <td rowspan="3" class="sticky left-[200px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[64px] min-w-[64px] align-middle text-center tabular-nums font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600" x-text="totalBobot().toFixed(2) + '%'"></td>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-t-2 border-r-gray-200 border-t-gray-300 dark:border-r-gray-700 dark:border-t-gray-600">Rencana</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'cp-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-t-2 border-gray-300 dark:border-gray-600" x-text="(hist.cumulative_plan || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-l border-t-2 border-l-gray-200 border-t-gray-300 dark:border-l-gray-700 dark:border-t-gray-600"
                            x-text="(progressHistory.length > 0 ? (progressHistory[progressHistory.length - 1].cumulative_plan || 0) : 0).toFixed(2) + '%'"></td>
                    </tr>
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">Aktual</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'ca-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white" x-text="(hist.cumulative_actual || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700"
                            x-text="(progressHistory.length > 0 ? (progressHistory[progressHistory.length - 1].cumulative_actual || 0) : 0).toFixed(2) + '%'"></td>
                    </tr>
                    <tr>
                        <td class="sticky left-[264px] z-10 bg-gray-50 dark:bg-gray-900 px-2 py-0.5 w-[84px] min-w-[84px] text-left font-bold text-gray-900 dark:text-white border-r border-gray-200 dark:border-gray-700">Deviasi</td>
                        <template x-for="(hist, weekIndex) in progressHistory" :key="'cd-' + weekIndex">
                        <td class="px-2 py-0.5 text-center tabular-nums font-bold"
                            :class="(hist.cumulative_deviation || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((hist.cumulative_deviation || 0) >= 0 ? '+' : '') + (hist.cumulative_deviation || 0).toFixed(2) + '%'"></td>
                        </template>
                        <td class="sticky right-0 z-10 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-center tabular-nums font-bold border-l border-gray-200 dark:border-gray-700"
                            :class="(progressHistory.length > 0 && (progressHistory[progressHistory.length - 1].cumulative_deviation || 0) >= 0) ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="(progressHistory.length > 0 ? (((progressHistory[progressHistory.length - 1].cumulative_deviation || 0) >= 0 ? '+' : '') + (progressHistory[progressHistory.length - 1].cumulative_deviation || 0).toFixed(2)) : '0.00') + '%'"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
