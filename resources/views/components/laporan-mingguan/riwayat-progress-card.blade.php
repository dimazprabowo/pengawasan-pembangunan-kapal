@props([
    'workGroups' => [],
    'progressHistory' => [],
    'fullProgressHistory' => null, // Pre-calculated full history from server (includes current week)
])

@if(count($progressHistory) > 0 || ($fullProgressHistory && count($fullProgressHistory) > 0))
<div {{ $attributes->merge(['class' => 'bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden']) }}>
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Riwayat Progress per Work Group</h3>
    </div>
    <div class="p-5"
         x-data="{
             bobots: @js(collect($workGroups)->mapWithKeys(fn($wg) => [(string)$wg['work_group_id'] => (float)$wg['bobot']])->all()),
             progressHistory: @js($fullProgressHistory ?? $progressHistory),
             init() {
                 @isset($fullProgressHistory)
                 this.$watch(
                     () => this.$wire.fullProgressHistory,
                     (val) => { if (Array.isArray(val)) this.progressHistory = val; }
                 );
                 @endisset
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
                 return Math.round(Object.keys(this.bobots).reduce((s, wgId) => s + this.historyPlanKontribusi(wgId, weekIndex), 0) * 100) / 100;
             },
             totalWeekActual(weekIndex) {
                 return Math.round(Object.keys(this.bobots).reduce((s, wgId) => s + this.historyKontribusi(wgId, weekIndex), 0) * 100) / 100;
             },
             weekDeviation(weekIndex) {
                 return Math.round((this.totalWeekActual(weekIndex) - this.totalWeekPlan(weekIndex)) * 100) / 100;
             },
             totalAllWeeksPlan() {
                 return Math.round(this.progressHistory.reduce((s, h, i) => s + this.totalWeekPlan(i), 0) * 100) / 100;
             },
             totalAllWeeksActual() {
                 return Math.round(this.progressHistory.reduce((s, h, i) => s + this.totalWeekActual(i), 0) * 100) / 100;
             },
             totalAllWeeksDeviation() {
                return Math.round((this.totalAllWeeksActual() - this.totalAllWeeksPlan()) * 100) / 100;
            }
         }"
         @progress-history-updated.window="progressHistory = $event.detail.history">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr class="bg-gray-50 dark:bg-gray-900">
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400" rowspan="2">Minggu</th>
                        @foreach($workGroups as $wg)
                        <th class="px-3 py-2 text-center font-medium text-gray-600 dark:text-gray-400 min-w-[120px]" rowspan="2">{{ $wg['nama'] }}</th>
                        @endforeach
                        <th class="px-3 py-2 text-center font-medium text-blue-600 dark:text-blue-400 border-l-2 border-blue-200 dark:border-blue-800" colspan="3">Per Minggu</th>
                        <th class="px-3 py-2 text-center font-medium text-purple-600 dark:text-purple-400 border-l-2 border-purple-200 dark:border-purple-800" colspan="3">Total Kumulatif</th>
                    </tr>
                    <tr class="bg-gray-50 dark:bg-gray-900">
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-blue-600 dark:text-blue-400 border-l-2 border-blue-200 dark:border-blue-800">Rencana</th>
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-blue-600 dark:text-blue-400">Aktual</th>
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-blue-600 dark:text-blue-400">Deviasi</th>
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-purple-600 dark:text-purple-400 border-l-2 border-purple-200 dark:border-purple-800">Rencana</th>
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-purple-600 dark:text-purple-400">Aktual</th>
                        <th class="px-2 py-1.5 text-center text-[10px] font-medium text-purple-600 dark:text-purple-400">Deviasi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <template x-for="(hist, weekIndex) in progressHistory" :key="weekIndex">
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                            <span class="font-medium" x-text="'Minggu ' + hist.minggu_ke"></span>
                            <span class="text-gray-400 ml-1" x-text="'(' + hist.created_at + ')'"></span>
                        </td>
                        <template x-for="wgId in Object.keys(bobots)" :key="wgId">
                        <td class="px-3 py-2 text-center">
                            <div class="space-y-0.5">
                                <div class="text-gray-500 dark:text-gray-400" x-text="'P: ' + historyPlanKontribusi(wgId, weekIndex).toFixed(2) + '%'"></div>
                                <div class="font-medium text-gray-700 dark:text-gray-300 tabular-nums" x-text="'A: ' + historyKontribusi(wgId, weekIndex).toFixed(2) + '%'"></div>
                                <div class="text-xs tabular-nums font-medium"
                                    :class="historyDeviation(wgId, weekIndex) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                    x-text="'D: ' + (historyDeviation(wgId, weekIndex) >= 0 ? '+' : '') + historyDeviation(wgId, weekIndex).toFixed(2) + '%'"></div>
                            </div>
                        </td>
                        </template>
                        {{-- Minggu Ini --}}
                        <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-400 tabular-nums font-medium border-l-2 border-blue-200 dark:border-blue-800" x-text="(hist.week_plan || 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-400 tabular-nums font-medium" x-text="(hist.week_actual || 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center tabular-nums font-medium"
                            :class="(hist.week_deviation || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((hist.week_deviation || 0) >= 0 ? '+' : '') + (hist.week_deviation || 0).toFixed(2) + '%'"></td>
                        {{-- Total Kumulatif --}}
                        <td class="px-2 py-2 text-center text-purple-600 dark:text-purple-400 tabular-nums font-semibold border-l-2 border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-900/10" x-text="(hist.cumulative_plan || 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center text-purple-700 dark:text-purple-300 tabular-nums font-semibold bg-purple-50/50 dark:bg-purple-900/10" x-text="(hist.cumulative_actual || 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center tabular-nums font-semibold bg-purple-50/50 dark:bg-purple-900/10"
                            :class="(hist.cumulative_deviation || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((hist.cumulative_deviation || 0) >= 0 ? '+' : '') + (hist.cumulative_deviation || 0).toFixed(2) + '%'"></td>
                    </tr>
                    </template>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 dark:bg-gray-800 font-semibold">
                        <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">Total Kontribusi (Semua Minggu)</td>
                        {{-- Total per Work Group dari backend --}}
                        <template x-if="progressHistory.length > 0 && progressHistory[progressHistory.length - 1].total_per_work_group">
                            <template x-for="wgId in Object.keys(bobots)" :key="wgId">
                            <td class="px-3 py-2 text-center">
                                <div class="space-y-0.5">
                                    <div class="text-gray-500 dark:text-gray-400" x-text="'P: ' + ((progressHistory[progressHistory.length - 1].total_per_work_group[wgId]?.plan || 0).toFixed(2)) + '%'"></div>
                                    <div class="text-gray-700 dark:text-gray-300 tabular-nums font-medium" x-text="'A: ' + ((progressHistory[progressHistory.length - 1].total_per_work_group[wgId]?.actual || 0).toFixed(2)) + '%'"></div>
                                    <div class="text-xs tabular-nums font-medium"
                                        :class="(progressHistory[progressHistory.length - 1].total_per_work_group[wgId]?.deviation || 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                                        x-text="'D: ' + ((progressHistory[progressHistory.length - 1].total_per_work_group[wgId]?.deviation || 0) >= 0 ? '+' : '') + ((progressHistory[progressHistory.length - 1].total_per_work_group[wgId]?.deviation || 0).toFixed(2)) + '%'"></div>
                                </div>
                            </td>
                            </template>
                        </template>
                        <template x-if="!progressHistory.length || !progressHistory[progressHistory.length - 1].total_per_work_group">
                            <template x-for="wgId in Object.keys(bobots)" :key="wgId">
                            <td class="px-3 py-2 text-center">
                                <div class="space-y-0.5">
                                    <div class="text-gray-500 dark:text-gray-400">P: 0.00%</div>
                                    <div class="text-gray-700 dark:text-gray-300 tabular-nums font-medium">A: 0.00%</div>
                                    <div class="text-xs tabular-nums font-medium text-gray-600 dark:text-gray-400">D: 0.00%</div>
                                </div>
                            </td>
                            </template>
                        </template>
                        {{-- Total Minggu Ini (dari backend) --}}
                        <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-400 tabular-nums font-bold border-l-2 border-blue-200 dark:border-blue-800" 
                            x-text="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_plan ? progressHistory[progressHistory.length - 1].cumulative_plan : 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center text-blue-600 dark:text-blue-400 tabular-nums font-bold" 
                            x-text="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_actual ? progressHistory[progressHistory.length - 1].cumulative_actual : 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center tabular-nums font-bold"
                            :class="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0) >= 0 ? '+' : '') + (progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0).toFixed(2) + '%'"></td>
                        {{-- Total Kumulatif (sama dengan total semua minggu) --}}
                        <td class="px-2 py-2 text-center text-purple-600 dark:text-purple-400 tabular-nums font-bold border-l-2 border-purple-200 dark:border-purple-800 bg-purple-100 dark:bg-purple-900/20" 
                            x-text="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_plan ? progressHistory[progressHistory.length - 1].cumulative_plan : 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center text-purple-700 dark:text-purple-300 tabular-nums font-bold bg-purple-100 dark:bg-purple-900/20" 
                            x-text="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_actual ? progressHistory[progressHistory.length - 1].cumulative_actual : 0).toFixed(2) + '%'"></td>
                        <td class="px-2 py-2 text-center tabular-nums font-bold bg-purple-100 dark:bg-purple-900/20"
                            :class="(progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                            x-text="((progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0) >= 0 ? '+' : '') + (progressHistory.length > 0 && progressHistory[progressHistory.length - 1].cumulative_deviation ? progressHistory[progressHistory.length - 1].cumulative_deviation : 0).toFixed(2) + '%'"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endif
