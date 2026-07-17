@props([
    'target' => null,
    'label' => 'Batal',
    'loadingText' => 'Memuat...',
    'icon' => false,
    'size' => 'lg',
])

@php
    $sizes = [
        'sm' => 'px-3 py-1.5 text-sm gap-1.5',
        'md' => 'px-3.5 py-2 text-sm gap-2',
        'lg' => 'px-4 py-2 text-base gap-2',
    ];

    $spinnerSizes = [
        'sm' => 'h-3.5 w-3.5',
        'md' => 'h-4 w-4',
        'lg' => 'h-5 w-5',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['lg'];
    $spinnerSize = $spinnerSizes[$size] ?? $spinnerSizes['lg'];

    $baseClass = $icon
        ? 'p-1.5 rounded-lg text-gray-400 hover:text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 transition disabled:opacity-50'
        : "inline-flex items-center justify-center font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed {$sizeClass}";
@endphp

@if($target)
    <button
        x-data="{ loading: false }"
        @click="loading = true; setTimeout(() => loading = false, 300)"
        :disabled="loading"
        {{ $attributes->merge(['type' => 'button', 'class' => $baseClass]) }}
    >
        @if($icon)
            <svg x-show="!loading" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
            <svg x-show="loading" x-cloak class="animate-spin h-6 w-6" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        @else
            <span x-show="!loading">{{ $label }}</span>
            <svg x-show="loading" x-cloak class="animate-spin {{ $spinnerSize }}" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            @if($loadingText)
                <span x-show="loading" x-cloak>{{ $loadingText }}</span>
            @endif
        @endif
    </button>
@else
    <button
        {{ $attributes->merge(['type' => 'button', 'class' => $baseClass]) }}
    >
        @if($icon)
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        @else
            {{ $label }}
        @endif
    </button>
@endif
