<div>
    @if($showModal)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity" wire:click="closeModal"></div>
        <div class="relative w-full max-w-5xl max-h-[92vh] flex flex-col bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700"
             x-data="{
                 n: @entangle('totalMinggu'),
                 _bobots: @js(collect($workGroups)->map(fn($wg) => (float)($wg['bobot'] ?? 0))->values()->all()),
                 _pcts: @js(collect($workGroups)->map(fn($wg) => collect($wg['weeks'] ?? [])->map(fn($w) => (float)($w['pct_rencana'] ?? 0))->values()->all())->values()->all()),
                 get totalBobot() {
                     return this._bobots.reduce((s, v) => s + (parseFloat(v) || 0), 0);
                 },
                 cumulative(gi, wi) {
                     const weeks = this._pcts[gi] || [];
                     let sum = 0;
                     for (let i = 0; i <= wi; i++) sum += parseFloat(weeks[i]) || 0;
                     return sum;
                 },
                 updateBobot(gi, val) {
                     const b = [...this._bobots];
                     b[gi] = parseFloat(val) || 0;
                     this._bobots = b;
                 },
                 updatePct(gi, wi, val) {
                     const p = this._pcts.map(a => [...(a || [])]);
                     if (!p[gi]) p[gi] = [];
                     p[gi][wi] = parseFloat(val) || 0;
                     this._pcts = p;
                 },
                 onInput(event) {
                     const t = event.target;
                     const gi = t.dataset.bobotGi;
                     const pgi = t.dataset.pctGi;
                     const pwi = t.dataset.pctWi;
                     if (gi !== undefined) this.updateBobot(parseInt(gi), t.value);
                     if (pgi !== undefined && pwi !== undefined) this.updatePct(parseInt(pgi), parseInt(pwi), t.value);
                 },
                 init() {
                     this.$wire.$watch('workGroups', (groups) => {
                         this._bobots = groups.map(wg => parseFloat(wg.bobot) || 0);
                         this._pcts = groups.map(wg => (wg.weeks || []).map(w => parseFloat(w.pct_rencana) || 0));
                     });
                 }
             }">

        {{-- ── Confirm: Hapus Work Group ─────────────────────────────────── --}}
        @if($confirmDeleteGroupIdx !== null)
        @php /** @var int $delGrpIdx */ $delGrpIdx = (int) $confirmDeleteGroupIdx; @endphp
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="cancelDeleteGroup"></div>
            <div class="relative z-10 w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Hapus Work Group?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Work group <strong>{{ $workGroups[$delGrpIdx]['nama'] ?: '(tanpa nama)' }}</strong> dan semua data rencana mingguannya akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteGroup"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Batal
                    </button>
                    <button type="button" wire:click="confirmDeleteGroup"
                        wire:loading.attr="disabled" wire:target="confirmDeleteGroup"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-75 flex items-center gap-1.5">
                        <svg wire:loading wire:target="confirmDeleteGroup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Confirm: Hapus Minggu ─────────────────────────────────────── --}}
        @if($confirmDeleteWeekGroupIdx !== null && $confirmDeleteWeekIdx !== null)
        @php /** @var int $delWkGrp */ $delWkGrp = (int) $confirmDeleteWeekGroupIdx; /** @var int $delWkIdx */ $delWkIdx = (int) $confirmDeleteWeekIdx; @endphp
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="cancelDeleteWeek"></div>
            <div class="relative z-10 w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Hapus Minggu ke-{{ $workGroups[$delWkGrp]['weeks'][$delWkIdx]['minggu_ke'] ?? '' }}?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Data rencana minggu ini akan dihapus dari work group <strong>{{ $workGroups[$delWkGrp]['nama'] ?: '(tanpa nama)' }}</strong>. Urutan minggu berikutnya akan disesuaikan.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteWeek"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        Batal
                    </button>
                    <button type="button" wire:click="confirmDeleteWeek"
                        wire:loading.attr="disabled" wire:target="confirmDeleteWeek"
                        class="px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors disabled:opacity-75 flex items-center gap-1.5">
                        <svg wire:loading wire:target="confirmDeleteWeek" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Ya, Hapus
                    </button>
                </div>
            </div>
        </div>
        @endif

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Kurva S — Work Groups & Rencana</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $jenisKapalNama }}</p>
                    </div>
                    <button type="button" wire:click="closeModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Action Buttons --}}
                <div class="p-4 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">Kelola Data Kurva S</p>
                                <p class="text-xs text-gray-600 dark:text-gray-400">Export atau import template Kurva S</p>
                            </div>
                        </div>
                        <x-kurva-s-actions />
                    </div>
                </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">

                {{-- Quick Set Minggu + Total Bobot --}}
                <div class="flex flex-col sm:flex-row gap-4">
                    {{-- Quick Set --}}
                    <div class="flex-1 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                        <p class="text-sm font-medium text-blue-800 dark:text-blue-300 mb-2">Jumlah Minggu Konstruksi</p>
                        <div class="flex items-center gap-2">
                            <input type="number" x-model.number="n" min="1" max="100"
                                class="w-20 px-3 py-1.5 text-sm border border-blue-300 dark:border-blue-600 rounded-lg focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-center">
                            <button type="button" x-on:click="$wire.setTotalMinggu(n)"
                                wire:loading.attr="disabled" wire:target="setTotalMinggu"
                                class="px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-75 flex items-center gap-1.5">
                                <svg wire:loading wire:target="setTotalMinggu" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                </svg>
                                <span wire:loading.remove wire:target="setTotalMinggu">Terapkan ke Semua Group</span>
                                <span wire:loading wire:target="setTotalMinggu">Menerapkan...</span>
                            </button>
                        </div>
                        <p class="text-xs text-blue-500 dark:text-blue-400 mt-1.5">Ini akan menyesuaikan jumlah minggu pada semua work group.</p>
                    </div>

                    {{-- Total Bobot (Alpine reactive) --}}
                    <div class="sm:w-72 p-4 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Total Bobot Work Groups</span>
                            <span class="text-sm font-bold"
                                :class="{
                                    'text-green-600 dark:text-green-400': Math.abs(totalBobot - 100) < 0.01,
                                    'text-red-600 dark:text-red-400': totalBobot > 100.01,
                                    'text-yellow-600 dark:text-yellow-400': totalBobot <= 99.99
                                }">
                                <span x-text="totalBobot.toFixed(2) + '%'"></span>
                                <span x-show="Math.abs(totalBobot - 100) < 0.01"> ✓</span>
                                <span x-show="totalBobot > 100.01"> ↑</span>
                                <span x-show="totalBobot <= 99.99 && totalBobot >= 0"> ↓</span>
                            </span>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all"
                                :class="{
                                    'bg-green-500': Math.abs(totalBobot - 100) < 0.01,
                                    'bg-red-500': totalBobot > 100.01,
                                    'bg-yellow-400': totalBobot <= 99.99
                                }"
                                :style="'width: ' + Math.min(100, totalBobot) + '%'"></div>
                        </div>
                        <p class="text-xs mt-1" x-show="Math.abs(totalBobot - 100) >= 0.01"
                            :class="{
                                'text-red-500': totalBobot > 100.01,
                                'text-yellow-600 dark:text-yellow-400': totalBobot <= 99.99
                            }"
                            x-text="totalBobot > 100.01 ? 'Total bobot melebihi 100%!' : 'Total bobot belum mencapai 100%'">
                        </p>
                    </div>
                </div>

                {{-- Work Groups List --}}
                @if(count($workGroups) === 0)
                    <div class="text-center py-12 text-gray-400 dark:text-gray-500">
                        <svg class="w-14 h-14 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                        </svg>
                        <p class="text-sm font-medium">Belum ada work group.</p>
                        <p class="text-xs mt-1">Klik "+ Tambah Work Group" untuk memulai.</p>
                    </div>
                @else
                    <div class="space-y-3" @input="onInput($event)">
                        @foreach($workGroups as $gi => $wg)
                        <div wire:key="wg-{{ $wg['id'] ?? $gi }}" class="border border-gray-200 dark:border-gray-600 rounded-lg overflow-hidden">

                            {{-- Work Group Row --}}
                            <div class="flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700/50">
                                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold flex items-center justify-center">
                                    {{ $gi + 1 }}
                                </span>

                                <div class="flex-1 min-w-0">
                                    <input type="text"
                                        wire:model.blur="workGroups.{{ $gi }}.nama"
                                        class="w-full px-2 py-1 text-sm font-medium bg-transparent border-b border-transparent hover:border-gray-300 dark:hover:border-gray-500 focus:border-blue-500 dark:focus:border-blue-400 focus:outline-none dark:text-white transition-colors
                                            @error('workGroups.'.$gi.'.nama') border-red-400 @enderror"
                                        placeholder="Nama Work Group (e.g. Engineering & Design)">
                                    @error('workGroups.'.$gi.'.nama')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="flex items-center gap-1.5 flex-shrink-0">
                                    <label class="text-xs text-gray-500 dark:text-gray-400">Bobot</label>
                                    <div class="relative w-24">
                                        <input type="number" step="0.01" min="0" max="100"
                                            wire:model.blur="workGroups.{{ $gi }}.bobot"
                                            data-bobot-gi="{{ $gi }}"
                                            class="w-full pr-6 pl-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                                @error('workGroups.'.$gi.'.bobot') border-red-500 @enderror"
                                            placeholder="0.00">
                                        <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                    </div>
                                </div>

                                <button type="button" wire:click="toggleGroup({{ $gi }})"
                                    class="flex-shrink-0 flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md transition-colors
                                        {{ $expandedGroupIdx === $gi
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                                    title="Edit rencana mingguan">
                                    <svg class="w-3.5 h-3.5 transition-transform {{ $expandedGroupIdx === $gi ? 'rotate-180' : '' }}"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    {{ count($wg['weeks']) }} minggu
                                </button>

                                <button type="button" wire:click="requestDeleteGroup({{ $gi }})"
                                    wire:loading.attr="disabled" wire:target="requestDeleteGroup({{ $gi }})"
                                    class="flex-shrink-0 text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 transition-colors p-1 disabled:opacity-50"
                                    title="Hapus work group">
                                    <svg wire:loading.class="hidden" wire:target="requestDeleteGroup({{ $gi }})" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <svg wire:loading wire:target="requestDeleteGroup({{ $gi }})" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Expanded Weekly Plan --}}
                            @if($expandedGroupIdx === $gi)
                            <div class="border-t border-gray-200 dark:border-gray-600 px-4 py-3">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 dark:bg-gray-900/30">
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-20">Minggu</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-36">Rencana (%)*</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Keterangan</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-28">Kumulatif Group</th>
                                                <th class="w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach($wg['weeks'] as $wi => $week)
                                                <tr wire:key="wg-{{ $wg['id'] ?? $gi }}-week-{{ $wi }}" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                                    <td class="px-3 py-1.5">
                                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-semibold">
                                                            {{ $week['minggu_ke'] }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-1.5">
                                                        <div class="relative">
                                                            <input type="number" step="0.01" min="0" max="100"
                                                                wire:model.blur="workGroups.{{ $gi }}.weeks.{{ $wi }}.pct_rencana"
                                                                data-pct-gi="{{ $gi }}" data-pct-wi="{{ $wi }}"
                                                                class="w-full pr-6 pl-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                                                    @error('workGroups.'.$gi.'.weeks.'.$wi.'.pct_rencana') border-red-500 @enderror"
                                                                placeholder="0.00">
                                                            <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-1.5">
                                                        <input type="text"
                                                            wire:model.blur="workGroups.{{ $gi }}.weeks.{{ $wi }}.keterangan"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                            placeholder="Opsional...">
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right">
                                                        <span class="text-sm font-medium tabular-nums"
                                                            :class="cumulative({{ $gi }}, {{ $wi }}) > 100 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'"
                                                            x-text="cumulative({{ $gi }}, {{ $wi }}).toFixed(2) + '%'">
                                                        </span>
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <button type="button"
                                                            wire:click="requestDeleteWeek({{ $gi }}, {{ $wi }})"
                                                            wire:loading.attr="disabled" wire:target="requestDeleteWeek({{ $gi }}, {{ $wi }})"
                                                            class="text-gray-300 hover:text-red-500 dark:text-gray-600 dark:hover:text-red-400 transition-colors p-0.5 disabled:opacity-50"
                                                            title="Hapus minggu ini">
                                                            <svg wire:loading.class="hidden" wire:target="requestDeleteWeek({{ $gi }}, {{ $wi }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                            </svg>
                                                            <svg wire:loading wire:target="requestDeleteWeek({{ $gi }}, {{ $wi }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <button type="button" wire:click="addWeekToGroup({{ $gi }})"
                                    wire:loading.attr="disabled" wire:target="addWeekToGroup({{ $gi }})"
                                    class="mt-2 w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded border border-dashed border-blue-300 dark:border-blue-700 transition-colors disabled:opacity-60">
                                    <svg wire:loading.class="hidden" wire:target="addWeekToGroup({{ $gi }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    <svg wire:loading wire:target="addWeekToGroup({{ $gi }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                    <span wire:loading.remove wire:target="addWeekToGroup({{ $gi }})">Tambah Minggu</span>
                                    <span wire:loading wire:target="addWeekToGroup({{ $gi }})">Menambahkan...</span>
                                </button>
                            </div>
                            @endif

                        </div>
                        @endforeach
                    </div>
                @endif

                {{-- Add Work Group --}}
                <button type="button" wire:click="addWorkGroup"
                    wire:loading.attr="disabled" wire:target="addWorkGroup"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400 border border-dashed border-blue-300 dark:border-blue-700 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors disabled:opacity-60">
                    <svg wire:loading.class="hidden" wire:target="addWorkGroup" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <svg wire:loading wire:target="addWorkGroup" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                    <span wire:loading.remove wire:target="addWorkGroup">Tambah Work Group</span>
                    <span wire:loading wire:target="addWorkGroup">Menambahkan...</span>
                </button>

            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex-shrink-0 bg-gray-50 dark:bg-gray-900/30 rounded-b-xl">
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-gray-700 dark:text-gray-200">{{ count($workGroups) }}</span> work group
                </span>
                <div class="flex items-center gap-3">
                    <button type="button" wire:click="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Batal
                    </button>
                    <x-loading-button wire:click="save" target="save" variant="primary" size="md" loadingText="Menyimpan...">
                        <x-slot:icon>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </x-slot:icon>
                        Simpan
                    </x-loading-button>
                </div>
            </div>

        </div>
    </div>
    @endif

    {{-- Import Modal --}}
    @if($showKurvaSImportModal)
        <x-kurva-s-import-modal 
            :show="$showKurvaSImportModal"
            :jenisKapalNama="$jenisKapalNama"
            wire:model="kurvaS_file"
        />
    @endif
</div>
