@props([
    'rows' => [],            // [['row_key','id','kendala','tindakan','pic']]
    'wirePrefix' => '',      // 'items.0.nc' (create) / 'nc' (edit)
    'argsPrefix' => '',      // '0, ' (create) / '' (edit)
    'addMethod' => 'addNc',  // 'addNc(0)' (create) / 'addNc' (edit)
    'existing' => collect(), // LaporanNc tersimpan, keyBy id (edit/show)
    'readonly' => false,
    'title' => 'Non-Conformity (NC)',
])

{{-- Section NC: kartu per NC — kendala (wajib), tindakan, PIC. Bukti before/after diunggah via List NC. --}}
<div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-3">
        <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2 min-w-0">
            <span class="truncate">{{ $title }}</span>
            @if(count($rows) > 0)
                <span class="min-w-[20px] px-1.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-[10px] leading-4 text-center text-amber-700 dark:text-amber-300">{{ count($rows) }}</span>
            @endif
        </h4>
        @unless($readonly)
            <button type="button" wire:click="{{ $addMethod }}"
                wire:loading.attr="disabled" wire:target="{{ $addMethod }}"
                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/20 dark:hover:bg-blue-900/30 rounded-lg transition-colors disabled:opacity-50">
                <svg wire:loading.class="hidden" wire:target="{{ $addMethod }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <svg wire:loading wire:target="{{ $addMethod }}" class="animate-spin w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                </svg>
                Tambah NC
            </button>
        @endunless
    </div>

    <div class="space-y-3">
    @forelse($rows as $i => $row)
        @php
            $rowKey = $row['row_key'] ?? $i;
            $nc = ! empty($row['id']) ? $existing->get($row['id']) : null;
            $isClosed = $nc?->isClosed() ?? false;
        @endphp
        <div wire:key="nc-row-{{ $rowKey }}-{{ $i }}"
            class="rounded-xl border shadow-sm {{ $isClosed ? 'border-emerald-200 dark:border-emerald-800/60 bg-emerald-50/50 dark:bg-emerald-900/10' : 'border-amber-200 dark:border-amber-800/50 bg-white dark:bg-gray-800' }} overflow-hidden">
            <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b {{ $isClosed ? 'border-emerald-100 dark:border-emerald-800/50 bg-emerald-50 dark:bg-emerald-900/20' : 'border-amber-100 dark:border-amber-800/40 bg-amber-50/60 dark:bg-amber-900/10' }}">
                <div class="flex items-center gap-2 min-w-0">
                    @if($nc?->nomor)
                        <span class="text-xs font-bold tracking-wide px-2 py-0.5 rounded-md bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-300">{{ $nc->nomor }}</span>
                    @else
                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">NC #{{ $i + 1 }}</span>
                    @endif
                    @if($nc)
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full {{ $nc->status->badgeClass() }}">{{ $nc->status->label() }}</span>
                    @endif
                </div>
                @unless($readonly || $isClosed)
                    <button type="button" wire:click="confirmRemoveNc({{ $argsPrefix }}{{ $i }})"
                        wire:loading.attr="disabled" wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})"
                        class="text-red-500 hover:text-red-700 p-1 disabled:opacity-50" title="Hapus NC">
                        <svg wire:loading.class="hidden" wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <svg wire:loading wire:target="confirmRemoveNc({{ $argsPrefix }}{{ $i }})" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                    </button>
                @endunless
            </div>

            @if($readonly)
                <div class="px-4 py-3 space-y-1.5">
                    <p class="text-sm text-gray-900 dark:text-white">{{ $row['kendala'] ?? '-' }}</p>
                    @if(!empty($row['tindakan']))
                        <p class="text-xs text-gray-500 dark:text-gray-400">Tindakan: {{ $row['tindakan'] }}</p>
                    @endif
                    @if(!empty($row['pic']))
                        <p class="text-xs text-gray-500 dark:text-gray-400">PIC: {{ $row['pic'] }}</p>
                    @endif
                </div>
            @else
            <fieldset {{ $isClosed ? 'disabled' : '' }} class="px-4 py-3 space-y-2 disabled:opacity-80">
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Kendala / Temuan <span class="text-red-500">*</span></label>
                    <textarea wire:model="{{ $wirePrefix }}.{{ $i }}.kendala" rows="2"
                        placeholder="Uraikan ketidaksesuaian yang ditemukan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800"></textarea>
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.kendala')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tindakan</label>
                    <textarea wire:model="{{ $wirePrefix }}.{{ $i }}.tindakan" rows="2"
                        placeholder="Rencana / tindakan perbaikan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800"></textarea>
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.tindakan')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">PIC</label>
                    <input type="text" wire:model="{{ $wirePrefix }}.{{ $i }}.pic"
                        placeholder="Penanggung jawab perbaikan..."
                        class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white disabled:bg-gray-50 dark:disabled:bg-gray-800">
                    <x-input-error :messages="$errors->get($wirePrefix.'.'.$i.'.pic')" class="mt-1" />
                </div>
            </fieldset>
            @endif

        </div>
    @empty
        <div class="py-8 text-center bg-white dark:bg-gray-800 border border-dashed border-gray-300 dark:border-gray-600 rounded-lg">
            <svg class="h-10 w-10 mx-auto text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                {{ $readonly ? 'Tidak ada NC pada laporan ini.' : 'Tidak ada temuan NC — klik "Tambah NC" bila ada ketidaksesuaian.' }}
            </p>
        </div>
    @endforelse
    </div>
</div>
