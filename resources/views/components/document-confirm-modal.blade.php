@props([
    'show' => false,
    'showProperty' => 'show', // The Livewire property name to watch
    'title' => 'Konfirmasi',
    'message' => 'Apakah Anda yakin?',
    'type' => 'danger', // 'regenerate' or 'delete'
    'docName' => null,
    'confirmAction' => 'confirm',
    'cancelAction' => 'cancel',
])

@if($show)
<div
    x-data
    x-init="
        document.body.style.overflow = 'hidden';
        $nextTick(() => $el.querySelector('[data-modal-panel]').focus());
        $watch('$wire.{{ $showProperty }}', (value) => {
            if (!value) {
                document.body.style.overflow = '';
            }
        });
    "
    x-on:keydown.escape.window="$wire.{{ $cancelAction }}()"
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    role="dialog"
    aria-modal="true"
    aria-labelledby="modal-title"
>
    {{-- Backdrop --}}
    <div
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        class="fixed inset-0 bg-gray-900/70 dark:bg-black/80 backdrop-blur-sm transition-opacity"
    ></div>

    {{-- Modal panel --}}
    <div
        data-modal-panel
        tabindex="-1"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-2xl shadow-[0_25px_60px_-10px_rgba(0,0,0,0.6)] dark:shadow-[0_25px_60px_-10px_rgba(0,0,0,0.9)] border border-gray-200 dark:border-gray-600 overflow-hidden outline-none"
    >
        {{-- Top accent stripe --}}
        <div class="h-1 @if($type === 'regenerate') bg-gradient-to-r from-amber-400 via-amber-500 to-orange-500 @else bg-gradient-to-r from-red-500 via-red-600 to-red-700 @endif"></div>

        {{-- Body --}}
        <div class="px-6 pt-5 pb-4">
            {{-- Icon + Title row --}}
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-11 h-11 rounded-full @if($type === 'regenerate') bg-amber-50 dark:bg-amber-900/20 ring-4 ring-amber-100 dark:ring-amber-900/30 @else bg-red-50 dark:bg-red-900/20 ring-4 ring-red-100 dark:ring-red-900/30 @endif flex items-center justify-center">
                    @if($type === 'regenerate')
                        <svg class="w-5 h-5 text-amber-500 dark:text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 text-red-500 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <h3 id="modal-title" class="text-base font-semibold text-gray-900 dark:text-white leading-snug">
                        {{ $title }}
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $message }}
                    </p>
                </div>
                <button
                    wire:click="{{ $cancelAction }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $cancelAction }},{{ $confirmAction }}"
                    class="flex-shrink-0 -mt-0.5 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
                    aria-label="Tutup"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Warning detail box --}}
            @if($docName)
            <div class="mt-4 rounded-xl border border-red-200 dark:border-red-700/70 bg-red-50 dark:bg-red-950/40 px-4 py-3.5">
                <div class="flex items-start gap-2.5">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
                    </svg>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold text-red-700 dark:text-red-400">File akan dihapus permanen</p>
                        <p class="mt-0.5 text-xs text-red-600 dark:text-red-400/90 font-mono truncate">{{ $docName }}</p>
                    </div>
                </div>
            </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="px-6 pb-5 pt-5 border-t border-gray-200 dark:border-gray-700 flex items-center justify-end gap-3">
            <button
                wire:click="{{ $cancelAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $cancelAction }},{{ $confirmAction }}"
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors disabled:opacity-50"
            >
                <svg wire:loading.class="hidden" wire:target="{{ $cancelAction }}" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <svg wire:loading wire:target="{{ $cancelAction }}" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="{{ $cancelAction }}">Batal</span>
                <span wire:loading wire:target="{{ $cancelAction }}">Membatalkan...</span>
            </button>
            <button
                wire:click="{{ $confirmAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $confirmAction }}"
                type="button"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg @if($type === 'regenerate') bg-amber-500 hover:bg-amber-600 shadow-sm shadow-amber-500/20 @else bg-red-600 hover:bg-red-700 shadow-sm shadow-red-500/20 @endif text-white transition-all duration-200 disabled:opacity-60"
            >
                <svg wire:loading.class="hidden" wire:target="{{ $confirmAction }}" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @if($type === 'regenerate')
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    @else
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    @endif
                </svg>
                <svg wire:loading wire:target="{{ $confirmAction }}" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span wire:loading.remove wire:target="{{ $confirmAction }}">
                    @if($type === 'regenerate') Ya, Generate Ulang @else Ya, Hapus @endif
                </span>
                <span wire:loading wire:target="{{ $confirmAction }}">
                    @if($type === 'regenerate') Memproses... @else Menghapus... @endif
                </span>
            </button>
        </div>
    </div>
</div>
@endif
