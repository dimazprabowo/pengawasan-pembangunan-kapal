@props([
    'wirePrefix' => '',
    'cuacaList' => [],
    'kelembabanList' => [],
])

@php
    $p = $wirePrefix ? $wirePrefix . '.' : '';
@endphp

<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
    <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">A. Kondisi Cuaca</h4>

    {{-- Pagi --}}
    <div class="mb-4">
        <div class="bg-orange-50 dark:bg-orange-900/10 rounded-lg p-4 border border-orange-200 dark:border-orange-800">
            <h5 class="text-sm font-medium text-orange-800 dark:text-orange-400 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Pagi
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                    <x-searchable-select
                        wire:model="{{ $p }}cuaca_pagi_id"
                        :options="$cuacaList"
                        placeholder="Pilih cuaca"
                        searchPlaceholder="Cari cuaca..."
                    />
                    @error($p . 'cuaca_pagi_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                    <x-searchable-select
                        wire:model="{{ $p }}kelembaban_pagi_id"
                        :options="$kelembabanList"
                        placeholder="Pilih kelembaban"
                        searchPlaceholder="Cari kelembaban..."
                    />
                    @error($p . 'kelembaban_pagi_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Siang --}}
    <div class="mb-4">
        <div class="bg-yellow-50 dark:bg-yellow-900/10 rounded-lg p-4 border border-yellow-200 dark:border-yellow-800">
            <h5 class="text-sm font-medium text-yellow-800 dark:text-yellow-400 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Siang
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                    <x-searchable-select
                        wire:model="{{ $p }}cuaca_siang_id"
                        :options="$cuacaList"
                        placeholder="Pilih cuaca"
                        searchPlaceholder="Cari cuaca..."
                    />
                    @error($p . 'cuaca_siang_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                    <x-searchable-select
                        wire:model="{{ $p }}kelembaban_siang_id"
                        :options="$kelembabanList"
                        placeholder="Pilih kelembaban"
                        searchPlaceholder="Cari kelembaban..."
                    />
                    @error($p . 'kelembaban_siang_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Sore --}}
    <div class="mb-4">
        <div class="bg-indigo-50 dark:bg-indigo-900/10 rounded-lg p-4 border border-indigo-200 dark:border-indigo-800">
            <h5 class="text-sm font-medium text-indigo-800 dark:text-indigo-400 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                </svg>
                Sore
            </h5>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Cuaca</label>
                    <x-searchable-select
                        wire:model="{{ $p }}cuaca_sore_id"
                        :options="$cuacaList"
                        placeholder="Pilih cuaca"
                        searchPlaceholder="Cari cuaca..."
                    />
                    @error($p . 'cuaca_sore_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Kelembaban</label>
                    <x-searchable-select
                        wire:model="{{ $p }}kelembaban_sore_id"
                        :options="$kelembabanList"
                        placeholder="Pilih kelembaban"
                        searchPlaceholder="Cari kelembaban..."
                    />
                    @error($p . 'kelembaban_sore_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Estimasi Suhu Lingkungan --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
            Estimasi Suhu Lingkungan (°C)
        </label>
        <input wire:model="{{ $p }}suhu" type="number" step="0.01"
            placeholder="Contoh: 30"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white">
        @error($p . 'suhu') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
    </div>
</div>
