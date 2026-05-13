@props([
    'chartData',          // array dari KurvaSService::getChartData()
    'jenisKapalNama' => null,
    'mingguKe'       => null,
    'showStats'      => true,
    'showMingguBadge'=> true,
    'height'         => '300px',
    'totalRencana'   => null,
    'totalAktual'    => null,
])

@if(!empty($chartData))
<div class="mt-6 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden mb-6">
    <div class="px-5 py-3.5 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
        </svg>
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Grafik Kurva S</h3>
        @if($jenisKapalNama)
            <span class="ml-auto text-xs text-gray-400 dark:text-gray-500">{{ $jenisKapalNama }}</span>
        @endif
    </div>
    <div class="p-5">
        @if($showMingguBadge && $mingguKe)
            <div class="mb-4 flex flex-wrap gap-3">
                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 rounded-lg">
                    <span class="text-xs text-emerald-600 dark:text-emerald-400 font-medium">Laporan Ini:</span>
                    <span class="text-xs text-emerald-700 dark:text-emerald-300">Minggu ke-{{ $mingguKe }}</span>
                </div>
            </div>
        @endif
        <x-kurva-s-chart
            :chartData="$chartData"
            :jenisKapalNama="$jenisKapalNama"
            :showStats="$showStats"
            :totalRencana="$totalRencana"
            :totalAktual="$totalAktual"
            :height="$height"
        />
    </div>
</div>
@endif
