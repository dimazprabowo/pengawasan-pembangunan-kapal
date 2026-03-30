@props([
    'target' => null,
    'variant' => 'primary',
    'size' => 'md',
    'loadingText' => null,
])

@php
    $variants = [
        'primary'   => 'bg-blue-600 hover:bg-blue-700 text-white focus:ring-blue-500',
        'success'   => 'bg-emerald-600 hover:bg-emerald-700 text-white focus:ring-emerald-500',
        'danger'    => 'bg-red-600 hover:bg-red-700 text-white focus:ring-red-500',
        'warning'   => 'bg-yellow-600 hover:bg-yellow-700 text-white focus:ring-yellow-500',
        'secondary' => 'bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 focus:ring-blue-500',
    ];

    $sizes = [
        'xs' => 'px-2 py-1 text-xs gap-1',
        'sm' => 'px-2.5 py-1.5 text-sm gap-1',
        'md' => 'px-3 py-2 text-sm gap-1.5',
        'lg' => 'px-4 py-2 text-base gap-2',
    ];

    $spinnerSizes = [
        'xs' => 'h-3 w-3',
        'sm' => 'h-3.5 w-3.5',
        'md' => 'h-4 w-4',
        'lg' => 'h-5 w-5',
    ];

    $hasIcon = isset($icon) && !$icon->isEmpty();
    $variantClass = $variants[$variant] ?? $variants['primary'];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    $spinnerSize = $spinnerSizes[$size] ?? $spinnerSizes['md'];
@endphp

<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => "inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 disabled:opacity-50 disabled:cursor-not-allowed whitespace-nowrap {$variantClass} {$sizeClass}",
    ]) }}
    @if($target)
        wire:loading.attr="disabled"
        wire:target="{{ $target }}"
    @endif
>
    {{-- Spinner (shown during loading) --}}
    @if($target)
        <svg wire:loading wire:target="{{ $target }}"
            class="animate-spin {{ $spinnerSize }}"
            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    @endif

    {{-- Icon slot (hidden during loading) --}}
    @if($hasIcon)
        <span @if($target) wire:loading.class.remove="inline-block" wire:loading.class.add="hidden" wire:target="{{ $target }}" @endif>
            {{ $icon }}
        </span>
    @endif

    {{-- Text (with optional loading text swap) --}}
    @if($target && $loadingText)
        <span wire:loading.class.remove="inline-block" wire:loading.class.add="hidden" wire:target="{{ $target }}">{{ $slot }}</span>
        <span wire:loading wire:target="{{ $target }}">{{ $loadingText }}</span>
    @else
        {{ $slot }}
    @endif
</button>
