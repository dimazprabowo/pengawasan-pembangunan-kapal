@props([
    'groups' => [],          // LaporanHarianService::groupRowsByKategori() / aktivitasPerKategori()
    'rowsModel' => '',       // path wire:model array aktivitas, mis. "items.0.aktivitas" / "aktivitas"
    'addPrefix' => '',       // prefix method tambah, mis. "addAktivitas(0, " / "addAktivitas("
    'deletePrefix' => '',    // prefix method hapus, mis. "confirmRemoveAktivitas(0, " / "confirmRemoveAktivitas("
    'wireKeyPrefix' => 'aktivitas',
    'persistKey' => '',      // key localStorage untuk mengingat sub-tab aktif (kosong = pakai wireKeyPrefix)
    'readonly' => false,
])

@php
    // Tab aktif awal: kategori pertama yang sudah berisi, fallback kategori pertama
    $defaultTab = collect($groups)->search(fn ($g) => count($g['rows'] ?? []) > 0);
    $defaultTab = $defaultTab === false ? 0 : $defaultTab;
    $persistKey = $persistKey !== '' ? $persistKey : 'lh-kat:'.$wireKeyPrefix;
@endphp

<div class="space-y-3">
    @unless($readonly)
        <div class="flex items-start gap-2 px-3 py-2.5 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xs text-blue-700 dark:text-blue-300">
                <span class="font-semibold">E. Uraian Aktivitas</span> — isi aktivitas pada kategori (Work Group Kurva-S) yang sesuai.
                Kategori tanpa aktivitas tidak akan ditampilkan di laporan. Foto per aktivitas diunggah di tab <span class="font-semibold">Lampiran</span>.
            </p>
        </div>
    @endunless

    <div x-data="{
            katTab: (function () {
                const v = parseInt(localStorage.getItem('{{ $persistKey }}'));
                return Number.isInteger(v) && v >= 0 && v < {{ count($groups) }} ? v : {{ $defaultTab }};
            })()
         }"
         x-init="$watch('katTab', v => localStorage.setItem('{{ $persistKey }}', v))">
        {{-- Sub-tab per kategori --}}
        <div class="flex gap-1 overflow-x-auto custom-scrollbar bg-gray-100 dark:bg-gray-900/60 rounded-xl p-1" role="tablist">
            @foreach($groups as $gi => $group)
                <button type="button" role="tab"
                    @click="katTab = {{ $gi }}; $nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' }))"
                    :class="katTab === {{ $gi }} ? 'bg-white dark:bg-gray-800 shadow-sm text-blue-600 dark:text-blue-400' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium whitespace-nowrap transition-all">
                    <span>{{ $group['nama'] }}</span>
                    @if(count($group['rows'] ?? []) > 0)
                        <span class="min-w-[18px] px-1 rounded-full bg-gray-200 dark:bg-gray-700 text-[10px] leading-4 text-center">{{ count($group['rows']) }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @foreach($groups as $gi => $group)
            @php $wgArg = $group['work_group_id'] ?? 'null'; @endphp
            <div x-show="katTab === {{ $gi }}" x-cloak
                wire:key="{{ $wireKeyPrefix }}-kategori-{{ $gi }}">
                <x-laporan.aktivitas-table
                    :title="$group['nama']"
                    :rows="$group['rows']"
                    :rowsModel="$rowsModel"
                    addMethod="{{ $addPrefix }}{{ $wgArg }})"
                    :deletePrefix="$deletePrefix"
                    wireKeyPrefix="{{ $wireKeyPrefix }}-{{ $gi }}"
                    :readonly="$readonly"
                />
            </div>
        @endforeach
    </div>
</div>
