@props([
    'groups' => [],          // LaporanHarianService::groupRowsByKategori($nc, $kategoriOptions)
    'wirePrefix' => '',      // 'items.0.nc' (create) / 'nc' (edit)
    'argsPrefix' => '',      // '0, ' (create) / '' (edit)
    'addPrefix' => '',       // prefix method tambah, mis. "addNc(0, " / "addNc("
    'wireKeyPrefix' => 'nc',
    'persistKey' => '',      // key localStorage untuk mengingat sub-tab aktif
    'existing' => collect(), // LaporanNc tersimpan keyBy id (edit/show)
    'readonly' => false,
])

@php
    $defaultTab = collect($groups)->search(fn ($g) => count($g['rows'] ?? []) > 0);
    $defaultTab = $defaultTab === false ? 0 : $defaultTab;
    $persistKey = $persistKey !== '' ? $persistKey : 'lh-nc-kat:'.$wireKeyPrefix;
@endphp

<div class="space-y-3">
    @unless($readonly)
        <div class="flex items-start gap-2 px-3 py-2.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <svg class="w-4 h-4 text-amber-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-xs text-amber-700 dark:text-amber-300">
                <span class="font-semibold">Non-Conformity (NC)</span> — catat temuan pada kategori (Work Group) yang sesuai.
                Bukti dokumen Before/After diunggah di <span class="font-semibold">List NC</span>.
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
                <x-laporan.nc-section
                    :title="$group['nama']"
                    :rows="$group['rows']"
                    :wirePrefix="$wirePrefix"
                    :argsPrefix="$argsPrefix"
                    addMethod="{{ $addPrefix }}{{ $wgArg }})"
                    :existing="$existing"
                    :readonly="$readonly"
                />
            </div>
        @endforeach
    </div>
</div>
