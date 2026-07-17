@props([
    'filters' => [],
])

<div class="relative w-full md:w-auto" x-data="{ filterOpen: false, dropUp: false,
        checkPosition() {
            const trigger = this.$refs.trigger;
            if (!trigger) return;
            const rect = trigger.getBoundingClientRect();
            const spaceBelow = window.innerHeight - rect.bottom;
            const spaceAbove = rect.top;
            const popoverHeight = 320;
            this.dropUp = spaceBelow < popoverHeight && spaceAbove > spaceBelow;
        },
        toggleFilter() {
            this.filterOpen = !this.filterOpen;
            if (this.filterOpen) {
                this.$nextTick(() => this.checkPosition());
            }
        }
    }" @click.outside="filterOpen = false" @scroll.window="filterOpen && checkPosition()">
    <button x-ref="trigger" @click="toggleFilter()" type="button"
        class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors w-full md:w-auto">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
        <span>Filter</span>
        <template x-if='[{{ collect($filters)->map(fn ($f) => "\$wire.{$f}")->join(', ') }}].filter(v => v !== "" && v !== null).length > 0'>
            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold text-white bg-blue-600 rounded-full"
                x-text='[{{ collect($filters)->map(fn ($f) => "\$wire.{$f}")->join(', ') }}].filter(v => v !== "" && v !== null).length'>
            </span>
        </template>
    </button>

    <div x-show="filterOpen" x-cloak
        :class="dropUp ? 'bottom-full mb-2' : 'top-full mt-2'"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 w-72 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-20 p-4">
        <div class="space-y-3">
            {{ $slot }}
        </div>

        <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <button @click="filterOpen = false" type="button" class="text-xs font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300">
                Tutup
            </button>
            <button wire:click="resetFilters" wire:loading.attr="disabled" wire:target="resetFilters" type="button"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:text-red-800 dark:hover:text-red-300 disabled:opacity-50">
                <svg wire:loading.class="hidden" wire:target="resetFilters" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <svg wire:loading wire:target="resetFilters" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                <span wire:loading.class="hidden" wire:target="resetFilters">Reset Filter</span>
                <span wire:loading wire:target="resetFilters">Loading</span>
            </button>
        </div>
    </div>
</div>
