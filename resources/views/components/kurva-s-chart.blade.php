@props([
    'chartData',          // array dari KurvaSService::getChartData()
    'jenisKapalNama' => null,
    'chartId'        => null,
    'showStats'      => true,
    'showEmptyState' => true,
    'height'         => '320px',
    'totalRencana'   => null,  // Optional: override total rencana from progress history
    'totalAktual'    => null,  // Optional: override total aktual from progress history
])

@php
    $uid = $chartId ?? 'kurvas-chart-' . uniqid();
    $hasRencana = $chartData['has_rencana'] ?? false;
    $hasAktual  = $chartData['has_aktual']  ?? false;
    $deviasi    = $chartData['deviasi']     ?? null;
    $terkini    = $chartData['progress_terkini'] ?? null;
    $total      = $totalRencana ?? ($chartData['total_bobot'] ?? 0);
    $aktual     = $totalAktual ?? null;

    // Calculate deviation if both total rencana and total aktual are provided
    if ($totalRencana !== null && $totalAktual !== null) {
        $deviasi = $totalAktual - $totalRencana;
    }
@endphp

<div>
    @if(!$hasRencana && $showEmptyState)
        <div class="flex flex-col items-center justify-center py-12 text-gray-400 dark:text-gray-500">
            <svg class="w-14 h-14 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <p class="text-sm font-medium">Kurva S belum dikonfigurasi</p>
            <p class="text-xs mt-1">Atur jadwal Kurva S di menu Jenis Kapal terlebih dahulu.</p>
        </div>
    @else
        {{-- Stats Cards — outside wire:ignore so they update on every Livewire re-render --}}
        @if($showStats && $hasRencana)
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Minggu</p>
                <p class="text-lg font-semibold text-gray-800 dark:text-white">{{ $chartData['total_minggu'] ?? 0 }}</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Total Rencana</p>
                <p class="text-lg font-semibold text-blue-600 dark:text-blue-400">{{ number_format($total, 2) }}%</p>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Progress Aktual</p>
                @if($aktual !== null)
                    <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($aktual, 2) }}%</p>
                @elseif($terkini !== null)
                    <p class="text-lg font-semibold text-emerald-600 dark:text-emerald-400">{{ number_format($terkini, 2) }}%</p>
                @else
                    <p class="text-lg font-semibold text-gray-400 dark:text-gray-500">—</p>
                @endif
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                <p class="text-xs text-gray-500 dark:text-gray-400 mb-0.5">Deviasi</p>
                @if($deviasi !== null)
                    <p class="text-lg font-semibold
                        @if($deviasi >= 0) text-emerald-600 dark:text-emerald-400
                        @else text-red-600 dark:text-red-400
                        @endif">
                        {{ $deviasi >= 0 ? '+' : '' }}{{ number_format($deviasi, 2) }}%
                    </p>
                @else
                    <p class="text-lg font-semibold text-gray-400 dark:text-gray-500">—</p>
                @endif
            </div>
        </div>
        @endif

        {{-- Chart Canvas — wire:ignore preserves Chart.js instance across Livewire re-renders.
             The kurva-s-updated event triggers updateData() to redraw with new data. --}}
        <div x-data="kurvaSChart(@js($chartData), '{{ $uid }}')"
             x-init="init()"
             @dark-mode-changed.window="updateColors()"
             @kurva-s-updated.window="updateData($event.detail.chartData)"
             wire:ignore>
            <div class="relative" style="height: {{ $height }}">
                <canvas id="{{ $uid }}"></canvas>
            </div>
        </div>

        {{-- Legend --}}
        <div class="flex items-center justify-center gap-6 mt-3">
            <div class="flex items-center gap-1.5">
                <div class="w-6 h-0.5 bg-blue-500 rounded-full"></div>
                <div class="w-2 h-2 rounded-full bg-blue-500 -ml-1"></div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Rencana</span>
            </div>
            <div class="flex items-center gap-1.5">
                <div class="w-6 h-0.5 bg-emerald-500 rounded-full"></div>
                <div class="w-2 h-2 rounded-full bg-emerald-500 -ml-1"></div>
                <span class="text-xs text-gray-500 dark:text-gray-400">Aktual</span>
            </div>
        </div>
    @endif
</div>
