@props([
    'period' => 'pagi', // pagi, siang, sore
    'cuacaId' => null,
    'kelembabanId' => null,
    'cuacaList' => [],
    'kelembabanList' => [],
    'wireModel' => '',
    'errors' => null,
    'readonly' => false,
])

@php
    $config = [
        'pagi' => [
            'label' => 'Pagi',
            'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z',
            'bgColor' => 'bg-orange-50 dark:bg-orange-900/10',
            'borderColor' => 'border-orange-200 dark:border-orange-800',
            'textColor' => 'text-orange-800 dark:text-orange-400',
        ],
        'siang' => [
            'label' => 'Siang',
            'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z',
            'bgColor' => 'bg-yellow-50 dark:bg-yellow-900/10',
            'borderColor' => 'border-yellow-200 dark:border-yellow-800',
            'textColor' => 'text-yellow-800 dark:text-yellow-400',
        ],
        'sore' => [
            'label' => 'Sore',
            'icon' => 'M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z',
            'bgColor' => 'bg-indigo-50 dark:bg-indigo-900/10',
            'borderColor' => 'border-indigo-200 dark:border-indigo-800',
            'textColor' => 'text-indigo-800 dark:text-indigo-400',
        ],
    ];
    
    $currentConfig = $config[$period] ?? $config['pagi'];
@endphp

<div class="md:col-span-2 mb-4">
    <div class="{{ $currentConfig['bgColor'] }} rounded-lg p-4 border {{ $currentConfig['borderColor'] }}">
        <h5 class="text-sm font-medium {{ $currentConfig['textColor'] }} mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $currentConfig['icon'] }}"/>
            </svg>
            {{ $currentConfig['label'] }}
        </h5>
        
        @if($readonly)
            {{-- Display Mode --}}
            <div class="space-y-2 text-xs">
                @if($cuacaId)
                    @php
                        $cuaca = collect($cuacaList)->firstWhere('id', $cuacaId);
                    @endphp
                    @if($cuaca)
                        <div class="flex items-center gap-2">
                            <span class="text-gray-600 dark:text-gray-400">Cuaca:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $cuaca['nama'] ?? $cuaca['label'] ?? '-' }}</span>
                        </div>
                    @endif
                @endif
                @if($kelembabanId)
                    @php
                        $kelembaban = collect($kelembabanList)->firstWhere('id', $kelembabanId);
                    @endphp
                    @if($kelembaban)
                        <div class="flex items-center gap-2">
                            <span class="text-gray-600 dark:text-gray-400">Kelembaban:</span>
                            <span class="font-medium text-gray-900 dark:text-white">{{ $kelembaban['nama'] ?? $kelembaban['label'] ?? '-' }}</span>
                        </div>
                    @endif
                @endif
            </div>
        @else
            {{-- Edit Mode --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                    <x-searchable-select
                        wire:model="{{ $wireModel }}.cuaca_{{ $period }}_id"
                        :options="$cuacaList"
                        placeholder="Pilih cuaca"
                        searchPlaceholder="Cari cuaca..."
                    />
                    @if($errors && $errors->has($wireModel . '.cuaca_' . $period . '_id'))
                        <span class="text-red-500 text-xs">{{ $errors->first($wireModel . '.cuaca_' . $period . '_id') }}</span>
                    @endif
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                    <x-searchable-select
                        wire:model="{{ $wireModel }}.kelembaban_{{ $period }}_id"
                        :options="$kelembabanList"
                        placeholder="Pilih kelembaban"
                        searchPlaceholder="Cari kelembaban..."
                    />
                    @if($errors && $errors->has($wireModel . '.kelembaban_' . $period . '_id'))
                        <span class="text-red-500 text-xs">{{ $errors->first($wireModel . '.kelembaban_' . $period . '_id') }}</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
