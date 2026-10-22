@props([
    'show' => false,
    'groups' => [],
    'confirmMethod' => 'saveWithReassign',
])

@php
    $showProp = $attributes->wire('model')->value();
    $totalDates = collect($groups)->sum(fn ($g) => count($g['dates'] ?? []));
@endphp

@if($show)
    <div class="fixed inset-0 z-[60] overflow-y-auto" x-data x-trap.noscroll="true">
        <div class="flex items-center justify-center min-h-screen px-4 py-6">
            <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/80" wire:click="$set('{{ $showProp }}', false)"></div>

            <div class="relative bg-white dark:bg-gray-800 rounded-2xl shadow-xl w-full max-w-lg z-10 overflow-hidden">
                {{-- Header --}}
                <div class="px-6 pt-6 pb-4 text-center">
                    <div class="w-12 h-12 rounded-full bg-yellow-100 dark:bg-yellow-900/30 flex items-center justify-center mx-auto mb-4">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white mb-1">Pindahkan Hari dari Minggu Lain?</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $totalDates }} hari yang dipilih sudah tercatat di laporan mingguan lain. Jika dilanjutkan, hari-hari berikut akan dilepas dari laporan tersebut dan dipindahkan ke laporan ini.
                    </p>
                </div>

                {{-- Conflict Groups --}}
                <div class="px-6 pb-2 max-h-64 overflow-y-auto space-y-3">
                    @foreach($groups as $group)
                        <div class="rounded-xl border border-yellow-200 dark:border-yellow-800/60 bg-yellow-50/60 dark:bg-yellow-900/10 p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-4 h-4 text-yellow-600 dark:text-yellow-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">{{ $group['judul'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $group['minggu_ke'] ? 'Minggu ke-'.$group['minggu_ke'] : 'Tanpa nomor minggu' }}{{ $group['periode'] ? ' · '.$group['periode'] : '' }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach($group['dates'] as $date)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-yellow-300 dark:border-yellow-700 text-xs font-medium text-yellow-800 dark:text-yellow-300">{{ $date }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-center gap-3 px-6 py-5">
                    <button wire:click="$set('{{ $showProp }}', false)" type="button"
                        wire:loading.attr="disabled"
                        wire:target="{{ $confirmMethod }}"
                        class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-700 rounded-xl transition disabled:opacity-50">
                        Batal
                    </button>
                    <button wire:click="{{ $confirmMethod }}" type="button"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-70 cursor-not-allowed"
                        wire:target="{{ $confirmMethod }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-yellow-600 hover:bg-yellow-700 text-white text-sm font-semibold rounded-xl shadow-sm transition-all">
                        <svg wire:loading wire:target="{{ $confirmMethod }}" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Ya, Pindahkan &amp; Simpan
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif
