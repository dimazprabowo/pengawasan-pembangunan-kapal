@props([
    'show' => false,
    'title' => 'Hapus Data',
    'message' => 'Yakin ingin menghapus data ini?',
    'itemName' => '',
    'confirmMethod' => 'delete',
])

@if($show)
    <div class="fixed inset-0 z-[60] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="$set('{{ $attributes->wire('model')->value() }}', false)"></div>
            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-sm z-10 p-6 text-center">
                <div class="w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">{{ $title }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
                    {{ $message }}
                    @if($itemName)
                        <span class="font-semibold text-gray-700 dark:text-white">{{ $itemName }}</span>?
                    @endif
                </p>
                <div class="flex items-center justify-center gap-3">
                    <button wire:click="$set('{{ $attributes->wire('model')->value() }}', false)" type="button"
                        wire:loading.attr="disabled"
                        wire:target="{{ $attributes->wire('model')->value() }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="{{ $attributes->wire('model')->value() }}" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="{{ $attributes->wire('model')->value() }}" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span wire:loading.remove wire:target="{{ $attributes->wire('model')->value() }}">Batal</span>
                        <span wire:loading wire:target="{{ $attributes->wire('model')->value() }}">Menutup...</span>
                    </button>
                    <button wire:click="{{ $confirmMethod }}" 
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all" 
                        wire:loading.attr="disabled" 
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        wire:target="{{ $confirmMethod }}">
                        <svg wire:loading wire:target="{{ $confirmMethod }}" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
