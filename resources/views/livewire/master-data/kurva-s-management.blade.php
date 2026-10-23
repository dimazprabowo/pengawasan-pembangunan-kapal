<div>
    @if($showModal)
    <div class="fixed inset-0 z-[60] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75 transition-opacity" wire:click="closeModal"></div>
        <div class="relative w-full max-w-5xl max-h-[92vh] flex flex-col bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700"
             x-data="{
                 _bobots: @js(collect($workGroups)->map(fn($wg) => (float)($wg['bobot'] ?? 0))->values()->all()),
                 _pcts: @js(collect($workGroups)->map(fn($wg) => collect($wg['months'] ?? [])->map(fn($w) => (float)($w['pct_rencana'] ?? 0))->values()->all())->values()->all()),
                 get totalBobot() {
                     return this._bobots.reduce((s, v) => s + (parseFloat(v) || 0), 0);
                 },
                 cumulative(gi, wi) {
                     const pcts = this._pcts[gi] || [];
                     let sum = 0;
                     for (let i = 0; i <= wi; i++) sum += parseFloat(pcts[i]) || 0;
                     return sum;
                 },
                 sumPct(gi) {
                     return (this._pcts[gi] || []).reduce((s, v) => s + (parseFloat(v) || 0), 0);
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
                 syncing: {},
                 syncFrom: {},
                 _blurTimers: {},
                 _inflight: {},
                 _watchdogs: {},
                 _syncTimer: null,
                 _syncSeq: 0,
                 scheduleSync() {
                     clearTimeout(this._syncTimer);
                     this._syncTimer = setTimeout(() => {
                         const seq = ++this._syncSeq;
                         this.$wire.syncDraft()
                             .catch(() => {})
                             .finally(() => {
                                 if (seq !== this._syncSeq) return;
                                 Object.keys(this.syncing).forEach(k => {
                                     const g = parseInt(k);
                                     if (!this.syncing[g] || this._inflight[g] > 0) return;
                                     this.clearGroup(g);
                                 });
                             });
                     }, 300);
                 },
                 markSyncing(gi, wi = null) {
                     this.syncing = { ...this.syncing, [gi]: true };
                     if (wi !== null) {
                         const cur = this.syncFrom[gi];
                         this.syncFrom = { ...this.syncFrom, [gi]: cur === undefined || wi < cur ? wi : cur };
                     }
                     clearTimeout(this._watchdogs[gi]);
                     this._watchdogs[gi] = setTimeout(() => {
                         this._inflight[gi] = 0;
                         this.clearGroup(gi);
                     }, 10000);
                 },
                 clearGroup(gi) {
                     clearTimeout(this._blurTimers[gi]);
                     clearTimeout(this._watchdogs[gi]);
                     this.syncing = { ...this.syncing, [gi]: false };
                     this.syncFrom = { ...this.syncFrom, [gi]: undefined };
                 },
                 fieldGroup(t) {
                     const gi = t.dataset.bobotGi ?? t.dataset.pctGi;
                     return gi === undefined ? null : parseInt(gi);
                 },
                 onInput(event) {
                     const t = event.target;
                     const gi = t.dataset.bobotGi;
                     const pgi = t.dataset.pctGi;
                     const pwi = t.dataset.pctWi;
                     if (gi !== undefined) {
                         this.updateBobot(parseInt(gi), t.value);
                         this.markSyncing(parseInt(gi));
                         this.scheduleSync();
                     }
                     if (pgi !== undefined && pwi !== undefined) {
                         this.updatePct(parseInt(pgi), parseInt(pwi), t.value);
                         this.markSyncing(parseInt(pgi), parseInt(pwi));
                         this.scheduleSync();
                     }
                 },
                 onFocusOut(event) {
                     const gi = this.fieldGroup(event.target);
                     if (gi === null || !this.syncing[gi] || this._inflight[gi]) return;
                     clearTimeout(this._blurTimers[gi]);
                     this._blurTimers[gi] = setTimeout(() => this.clearGroup(gi), 1000);
                 },
                 _unhookCommit: null,
                 init() {
                     this.$wire.$watch('workGroups', (groups) => {
                         this._bobots = groups.map(wg => parseFloat(wg.bobot) || 0);
                         this._pcts = groups.map(wg => (wg.months || []).map(w => parseFloat(w.pct_rencana) || 0));
                     });
                     this._unhookCommit = Livewire.hook('commit', ({ component, commit, respond, fail }) => {
                         if (component.id !== this.$wire.$id) return;
                         const groups = new Set();
                         Object.keys(commit.updates || {}).forEach(key => {
                             const p = key.split('.');
                             if (p[0] !== 'workGroups' || ['nama', 'keterangan', 'id', 'bulan_ke'].includes(p[p.length - 1])) return;
                             if (p.length === 1) {
                                 this._pcts.forEach((_, gi) => { groups.add(gi); this.markSyncing(gi, -1); });
                             } else {
                                 const gi = parseInt(p[1]);
                                 groups.add(gi);
                                 this.markSyncing(gi, p[2] === 'bobot' ? null : (p[2] === 'months' && p[3] !== undefined ? parseInt(p[3]) : -1));
                             }
                         });
                         if (!groups.size && !Object.values(this.syncing).some(Boolean)) return;
                         groups.forEach(gi => {
                             clearTimeout(this._blurTimers[gi]);
                             this._inflight[gi] = (this._inflight[gi] || 0) + 1;
                         });
                         const release = () => {
                             groups.forEach(gi => {
                                 this._inflight[gi] = Math.max(0, (this._inflight[gi] || 1) - 1);
                             });
                             Object.keys(this.syncing).forEach(k => {
                                 const g = parseInt(k);
                                 if (!this.syncing[g] || this._inflight[g] > 0) return;
                                 this.clearGroup(g);
                             });
                         };
                         fail(release);
                         respond(release);
                     });
                 },
                 destroy() {
                     this._unhookCommit?.();
                     clearTimeout(this._syncTimer);
                     Object.values(this._blurTimers).forEach(t => clearTimeout(t));
                     Object.values(this._watchdogs).forEach(t => clearTimeout(t));
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
                            Work group <strong>{{ $workGroups[$delGrpIdx]['nama'] ?: '(tanpa nama)' }}</strong> dan semua data rencana bulanannya akan dihapus. Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelDeleteGroup"
                        wire:loading.attr="disabled" wire:target="cancelDeleteGroup"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 flex items-center gap-1.5">
                        <svg wire:loading wire:target="cancelDeleteGroup" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <span wire:loading.remove wire:target="cancelDeleteGroup">Batal</span>
                        <span wire:loading wire:target="cancelDeleteGroup">Menutup...</span>
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

        {{-- ── Confirm: Reset Nilai Bulan ──────────────────────────────── --}}
        @if($confirmResetMonthGroupIdx !== null && $confirmResetMonthIdx !== null)
        @php /** @var int $resetWkGrp */ $resetWkGrp = (int) $confirmResetMonthGroupIdx; /** @var int $resetWkIdx */ $resetWkIdx = (int) $confirmResetMonthIdx; @endphp
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="cancelResetMonth"></div>
            <div class="relative z-10 w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Reset Rencana Bulan ke-{{ $workGroups[$resetWkGrp]['months'][$resetWkIdx]['bulan_ke'] ?? '' }}?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Nilai rencana dan keterangan bulan ini pada work group <strong>{{ $workGroups[$resetWkGrp]['nama'] ?: '(tanpa nama)' }}</strong> akan dikosongkan. Slot bulan tidak dihapus dan urutan bulan lain tidak berubah.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelResetMonth"
                        wire:loading.attr="disabled" wire:target="cancelResetMonth"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 flex items-center gap-1.5">
                        <svg wire:loading wire:target="cancelResetMonth" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <span wire:loading.remove wire:target="cancelResetMonth">Batal</span>
                        <span wire:loading wire:target="cancelResetMonth">Menutup...</span>
                    </button>
                    <button type="button" wire:click="confirmResetMonth"
                        wire:loading.attr="disabled" wire:target="confirmResetMonth"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors disabled:opacity-75 flex items-center gap-1.5">
                        <svg wire:loading wire:target="confirmResetMonth" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Ya, Reset
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Confirm: Terapkan Bulan Kontrak ──────────────────────────── --}}
        @if($confirmApplyMonths)
        <div class="fixed inset-0 z-[80] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60" wire:click="cancelApplyContractMonths"></div>
            <div class="relative z-10 w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-2xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900/30">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-base font-semibold text-gray-900 dark:text-white">Terapkan {{ $this->kontrakSummary['total_bulan'] ?? 0 }} Bulan ke Semua Work Group?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Jumlah bulan <strong>semua work group</strong> akan disesuaikan ke <strong>{{ $this->kontrakSummary['total_bulan'] ?? 0 }}</strong> bulan sesuai kontrak.
                            Work group yang memiliki lebih dari {{ $this->kontrakSummary['total_bulan'] ?? 0 }} bulan akan <strong class="text-red-600 dark:text-red-400">kehilangan bulan berlebih beserta nilai rencananya</strong>.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="cancelApplyContractMonths"
                        wire:loading.attr="disabled" wire:target="cancelApplyContractMonths"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors disabled:opacity-50 flex items-center gap-1.5">
                        <svg wire:loading wire:target="cancelApplyContractMonths" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <span wire:loading.remove wire:target="cancelApplyContractMonths">Batal</span>
                        <span wire:loading wire:target="cancelApplyContractMonths">Menutup...</span>
                    </button>
                    <button type="button" wire:click="confirmApplyContractMonths"
                        wire:loading.attr="disabled" wire:target="confirmApplyContractMonths"
                        class="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg transition-colors disabled:opacity-75 flex items-center gap-1.5">
                        <svg wire:loading wire:target="confirmApplyContractMonths" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        Ya, Terapkan
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
                        wire:loading.attr="disabled" wire:target="closeModal"
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1 rounded disabled:opacity-50">
                        <svg wire:loading.class="hidden" wire:target="closeModal" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <svg wire:loading wire:target="closeModal" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
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

                {{-- Periode Kontrak --}}
                <div class="p-4 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg"
                    x-data="{ detailTab: 'minggu' }">
                    <p class="text-sm font-medium text-emerald-800 dark:text-emerald-300 mb-1">Periode Kontrak (Hari Kalender)</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-400 mb-3">
                        Periode laporan mingguan &amp; bulanan akan digenerate otomatis mengikuti kontrak ini.
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] gap-3 items-end">
                        <div>
                            <label for="tanggal_mulai_kontrak" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Tanggal Mulai Kontrak</label>
                            <x-tanggal-picker wire:model.live="tanggal_mulai_kontrak"
                                display-format="j F Y"
                                placeholder="Pilih tanggal mulai kontrak"
                                class="!py-1.5 text-sm" />
                            @error('tanggal_mulai_kontrak')
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="durasi_kontrak_hari" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Durasi Kontrak (hari)</label>
                            <input id="durasi_kontrak_hari" type="number"
                                wire:model.live.debounce.500ms="durasi_kontrak_hari"
                                wire:key="durasi-kontrak-{{ $jenisKapalId }}"
                                wire:ignore
                                value="{{ $durasi_kontrak_hari }}"
                                min="1" max="7300" step="1"
                                placeholder="Contoh: 300"
                                class="w-full px-3 py-1.5 text-sm border rounded-lg focus:ring-2 dark:bg-gray-700 dark:text-white @error('durasi_kontrak_hari') border-red-500 dark:border-red-500 focus:ring-red-500 @else border-emerald-300 dark:border-emerald-600 focus:ring-emerald-500 @enderror">
                            @error('durasi_kontrak_hari')
                                <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="sm:col-span-2 lg:col-span-1">
                            <x-loading-button wire:click="requestApplyContractMonths" target="requestApplyContractMonths" variant="success" size="sm"
                                loadingText="Memeriksa..." title="Sesuaikan jumlah bulan semua work group ke total bulan kontrak"
                                class="w-full lg:w-auto">
                                <x-slot:icon>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4.5 9a8 8 0 0114.32-3.36M19.5 15a8 8 0 01-14.32 3.36"/>
                                    </svg>
                                </x-slot:icon>
                                Terapkan Bulan ke Semua Group
                            </x-loading-button>
                        </div>
                    </div>

                    @if($this->kontrakSummary)
                        {{-- Ringkasan hasil generate sistem --}}
                        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <div class="flex items-center gap-2.5 px-3 py-2 bg-white/80 dark:bg-emerald-950/40 rounded-lg border border-emerald-200/80 dark:border-emerald-800/60">
                                <svg class="w-4 h-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-600/80 dark:text-emerald-400/80">Tanggal Selesai</p>
                                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100 truncate">
                                        <span wire:loading.class="hidden" wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak">{{ $this->kontrakSummary['selesai'] }}</span>
                                        <svg wire:loading wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak" class="animate-spin w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                        </svg>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 px-3 py-2 bg-white/80 dark:bg-emerald-950/40 rounded-lg border border-emerald-200/80 dark:border-emerald-800/60">
                                <svg class="w-4 h-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-600/80 dark:text-emerald-400/80">Minggu Proyek</p>
                                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                        <span wire:loading.class="hidden" wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak">{{ $this->kontrakSummary['total_minggu'] }} minggu</span>
                                        <svg wire:loading wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak" class="animate-spin w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                        </svg>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2.5 px-3 py-2 bg-white/80 dark:bg-emerald-950/40 rounded-lg border border-emerald-200/80 dark:border-emerald-800/60">
                                <svg class="w-4 h-4 flex-shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-medium uppercase tracking-wide text-emerald-600/80 dark:text-emerald-400/80">Bulan Kalender</p>
                                    <p class="text-sm font-semibold text-emerald-900 dark:text-emerald-100">
                                        <span wire:loading.class="hidden" wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak">{{ $this->kontrakSummary['total_bulan'] }} bulan</span>
                                        <svg wire:loading wire:target="durasi_kontrak_hari,tanggal_mulai_kontrak" class="animate-spin w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                        </svg>
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Toggle detail periode (lazy render — tabel baru digenerate saat dibuka) --}}
                        <button type="button" wire:click="togglePeriodeDetail"
                            wire:loading.attr="disabled" wire:target="togglePeriodeDetail"
                            class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-300 hover:text-emerald-900 dark:hover:text-emerald-100 transition-colors disabled:opacity-50">
                            <svg wire:loading.class="hidden" wire:target="togglePeriodeDetail"
                                class="w-3.5 h-3.5 transition-transform {{ $showPeriodeDetail ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                            <svg wire:loading wire:target="togglePeriodeDetail" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                            </svg>
                            {{ $showPeriodeDetail ? 'Sembunyikan detail periode' : 'Lihat detail periode (preview generate sistem)' }}
                        </button>

                        @if($showPeriodeDetail)
                        <div x-transition.opacity.duration.200ms class="mt-2">
                            {{-- Tab Minggu / Bulan --}}
                            <div class="inline-flex items-center gap-1 p-0.5 bg-emerald-100/70 dark:bg-emerald-900/40 rounded-lg mb-2">
                                <button type="button" x-on:click="detailTab = 'minggu'"
                                    class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
                                    :class="detailTab === 'minggu' ? 'bg-white dark:bg-emerald-800 text-emerald-800 dark:text-emerald-100 shadow-sm' : 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200'">
                                    Minggu Proyek ({{ $this->kontrakSummary['total_minggu'] }})
                                </button>
                                <button type="button" x-on:click="detailTab = 'bulan'"
                                    class="px-3 py-1 text-xs font-medium rounded-md transition-colors"
                                    :class="detailTab === 'bulan' ? 'bg-white dark:bg-emerald-800 text-emerald-800 dark:text-emerald-100 shadow-sm' : 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-800 dark:hover:text-emerald-200'">
                                    Bulan Kalender ({{ $this->kontrakSummary['total_bulan'] }})
                                </button>
                            </div>

                            {{-- Tabel minggu --}}
                            <div x-show="detailTab === 'minggu'" class="max-h-56 overflow-auto custom-scrollbar bg-white/80 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60 rounded-lg">
                                <table class="min-w-full text-xs">
                                    <thead class="sticky top-0 bg-emerald-100 dark:bg-emerald-900">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 w-24 whitespace-nowrap">Minggu</th>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 whitespace-nowrap">Periode Tanggal</th>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 w-20 whitespace-nowrap">Bulan</th>
                                            <th class="px-3 py-1.5 text-right font-semibold text-emerald-800 dark:text-emerald-200 w-16 whitespace-nowrap">Hari</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100 dark:divide-emerald-900/50">
                                        @foreach(($this->periodePreview['minggu'] ?? []) as $m)
                                        <tr wire:key="pv-minggu-{{ $m['minggu_ke'] }}" class="hover:bg-emerald-50/70 dark:hover:bg-emerald-900/30">
                                            <td class="px-3 py-1 font-medium text-emerald-800 dark:text-emerald-200 whitespace-nowrap">Minggu Ke - {{ $m['minggu_ke'] }}</td>
                                            <td class="px-3 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ $m['periode'] }}</td>
                                            <td class="px-3 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap">@if($m['bulan_ke']) Bulan Ke - {{ $m['bulan_ke'] }} @else — @endif</td>
                                            <td class="px-3 py-1 text-right text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ $m['total_hari'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{-- Tabel bulan --}}
                            <div x-show="detailTab === 'bulan'" class="max-h-56 overflow-auto custom-scrollbar bg-white/80 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60 rounded-lg">
                                <table class="min-w-full text-xs">
                                    <thead class="sticky top-0 bg-emerald-100 dark:bg-emerald-900">
                                        <tr>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 w-24 whitespace-nowrap">Bulan</th>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 whitespace-nowrap">Nama</th>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 w-32 whitespace-nowrap">Cakupan Minggu</th>
                                            <th class="px-3 py-1.5 text-left font-semibold text-emerald-800 dark:text-emerald-200 whitespace-nowrap">Periode Tanggal</th>
                                            <th class="px-3 py-1.5 text-right font-semibold text-emerald-800 dark:text-emerald-200 w-16 whitespace-nowrap">Hari</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100 dark:divide-emerald-900/50">
                                        @foreach(($this->periodePreview['bulan'] ?? []) as $b)
                                        <tr wire:key="pv-bulan-{{ $b['bulan_ke'] }}" class="hover:bg-emerald-50/70 dark:hover:bg-emerald-900/30">
                                            <td class="px-3 py-1 font-medium text-emerald-800 dark:text-emerald-200 whitespace-nowrap">Bulan Ke - {{ $b['bulan_ke'] }}</td>
                                            <td class="px-3 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap">Periode {{ $b['nama'] }}</td>
                                            <td class="px-3 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap">
                                                Minggu {{ $b['minggu_dari'] }}@if($b['minggu_sampai'] !== $b['minggu_dari']) – {{ $b['minggu_sampai'] }}@endif
                                                <span class="text-emerald-500/80 dark:text-emerald-400/70">({{ $b['total_minggu'] }} mgg)</span>
                                            </td>
                                            <td class="px-3 py-1 text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ $b['periode'] }}</td>
                                            <td class="px-3 py-1 text-right text-emerald-700 dark:text-emerald-300 whitespace-nowrap">{{ $b['total_hari'] }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    @else
                        <p class="mt-3 text-xs text-emerald-600/80 dark:text-emerald-400/80 italic">
                            Isi tanggal mulai &amp; durasi untuk melihat ringkasan periode kontrak.
                        </p>
                    @endif
                </div>

                {{-- Total Bobot (Alpine reactive) — jumlah bulan diturunkan dari kontrak, tidak diinput manual --}}
                <div class="p-4 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg">
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
                    <div class="space-y-3" @input="onInput($event)" @focusout="onFocusOut($event)">
                        @foreach($workGroups as $gi => $wg)
                        <div wire:key="wg-{{ $wg['id'] ?? 'new' }}-{{ $gi }}" class="border border-gray-200 dark:border-gray-600 rounded-lg">

                            {{-- Work Group Row (sticky saat group di-expand & modal di-scroll) --}}
                            <div class="sticky -top-5 z-10 flex items-center gap-3 px-4 py-3 bg-gray-50 dark:bg-gray-700 {{ $expandedGroupIdx === $gi ? 'rounded-t-lg' : 'rounded-lg' }}">
                                <span class="flex-shrink-0 w-7 h-7 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-bold flex items-center justify-center">
                                    {{ $gi + 1 }}
                                </span>

                                <div class="flex-1 min-w-0">
                                    <input type="text"
                                        wire:model.unintrusive.blur="workGroups.{{ $gi }}.nama"
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
                                            wire:model.unintrusive="workGroups.{{ $gi }}.bobot"
                                            data-bobot-gi="{{ $gi }}"
                                            class="w-full pr-6 pl-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                                @error('workGroups.'.$gi.'.bobot') border-red-500 @enderror"
                                            placeholder="0.00">
                                        <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                    </div>
                                </div>

                                {{-- Σ rencana vs bobot — indikator ketercapaian bobot kategori --}}
                                <span class="flex-shrink-0 inline-flex items-center gap-1 text-xs tabular-nums"
                                    :class="sumPct({{ $gi }}) > (_bobots[{{ $gi }}] || 0) + 0.01
                                        ? 'text-red-600 dark:text-red-400 font-semibold'
                                        : (Math.abs(sumPct({{ $gi }}) - (_bobots[{{ $gi }}] || 0)) <= 0.01
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-yellow-600 dark:text-yellow-400')"
                                    :title="'Total rencana terdistribusi: ' + sumPct({{ $gi }}).toFixed(2) + '% dari bobot ' + (_bobots[{{ $gi }}] || 0).toFixed(2) + '%'">
                                    <svg x-show="syncing[{{ $gi }}]" x-cloak class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                    <span x-show="!syncing[{{ $gi }}]" x-text="'Σ ' + sumPct({{ $gi }}).toFixed(2) + '%'"></span>
                                </span>

                                <button type="button" wire:click="toggleGroup({{ $gi }})"
                                    wire:loading.attr="disabled" wire:target="toggleGroup({{ $gi }})"
                                    class="flex-shrink-0 flex items-center gap-1 px-2.5 py-1 text-xs font-medium rounded-md transition-colors disabled:opacity-60
                                        {{ $expandedGroupIdx === $gi
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                            : 'text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-600' }}"
                                    title="Edit rencana bulanan">
                                    <svg wire:loading.class="hidden" wire:target="toggleGroup({{ $gi }})" class="w-3.5 h-3.5 transition-transform {{ $expandedGroupIdx === $gi ? 'rotate-180' : '' }}"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                    <svg wire:loading wire:target="toggleGroup({{ $gi }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                    </svg>
                                    {{ count($wg['months']) }} bulan
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

                            {{-- Expanded Monthly Plan — rencana per bulan; nilai bulan dibagi rata ke minggunya --}}
                            @if($expandedGroupIdx === $gi)
                            <div class="border-t border-gray-200 dark:border-gray-600 px-4 py-3 rounded-b-lg">
                                <div class="overflow-x-auto custom-scrollbar">
                                    <table class="min-w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 dark:bg-gray-900/30">
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-44">Bulan</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-36">Rencana (%)*</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Keterangan</th>
                                                <th class="px-3 py-1.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase w-28">Kumulatif</th>
                                                <th class="w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                            @foreach($wg['months'] as $mi => $month)
                                                @php $bulanMeta = $this->bulanMeta[(int) $month['bulan_ke']] ?? null; @endphp
                                                <tr wire:key="wg-{{ $wg['id'] ?? 'new' }}-{{ $gi }}-month-{{ $mi }}" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/20">
                                                    <td class="px-3 py-1.5">
                                                        <div class="flex items-center gap-2 whitespace-nowrap">
                                                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-xs font-semibold">
                                                                {{ $month['bulan_ke'] }}
                                                            </span>
                                                            <div class="leading-tight">
                                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $bulanMeta['nama'] ?? '—' }}</p>
                                                                <p class="text-[10px] text-gray-400 dark:text-gray-500">{{ $bulanMeta['cakupan'] ?? '' }}</p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-1.5">
                                                        <div class="relative">
                                                            <input type="number" step="0.01" min="0" max="100"
                                                                wire:model.unintrusive="workGroups.{{ $gi }}.months.{{ $mi }}.pct_rencana"
                                                                data-pct-gi="{{ $gi }}" data-pct-wi="{{ $mi }}"
                                                                class="w-full pr-6 pl-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white text-right
                                                                    @error('workGroups.'.$gi.'.months.'.$mi.'.pct_rencana') border-red-500 @enderror"
                                                                placeholder="0.00">
                                                            <span class="absolute right-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">%</span>
                                                        </div>
                                                    </td>
                                                    <td class="px-3 py-1.5">
                                                        <input type="text"
                                                            wire:model.unintrusive.blur="workGroups.{{ $gi }}.months.{{ $mi }}.keterangan"
                                                            class="w-full px-2 py-1 text-sm border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-blue-500 dark:bg-gray-700 dark:text-white"
                                                            placeholder="Opsional...">
                                                    </td>
                                                    <td class="px-3 py-1.5 text-right">
                                                        <span x-show="!(syncing[{{ $gi }}] && syncFrom[{{ $gi }}] !== undefined && {{ $mi }} >= syncFrom[{{ $gi }}])"
                                                            class="text-sm font-medium tabular-nums"
                                                            :class="cumulative({{ $gi }}, {{ $mi }}) > (_bobots[{{ $gi }}] || 0) + 0.01 ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-300'"
                                                            x-text="cumulative({{ $gi }}, {{ $mi }}).toFixed(2) + '%'">
                                                        </span>
                                                        <svg x-show="syncing[{{ $gi }}] && syncFrom[{{ $gi }}] !== undefined && {{ $mi }} >= syncFrom[{{ $gi }}]" x-cloak
                                                            class="animate-spin w-3.5 h-3.5 inline-block text-blue-500" fill="none" viewBox="0 0 24 24">
                                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                                                        </svg>
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <button type="button"
                                                            wire:click="requestResetMonth({{ $gi }}, {{ $mi }})"
                                                            wire:loading.attr="disabled" wire:target="requestResetMonth({{ $gi }}, {{ $mi }})"
                                                            class="text-gray-300 hover:text-amber-500 dark:text-gray-600 dark:hover:text-amber-400 transition-colors p-0.5 disabled:opacity-50"
                                                            title="Reset nilai bulan ini (slot tidak dihapus)">
                                                            <svg wire:loading.class="hidden" wire:target="requestResetMonth({{ $gi }}, {{ $mi }})" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9.75L14.25 12m0 0l2.25 2.25M14.25 12l2.25-2.25M14.25 12L12 14.25m-2.58 4.92l-6.375-6.375a1.125 1.125 0 010-1.59L9.42 4.83c.211-.211.498-.33.796-.33H19.5a2.25 2.25 0 012.25 2.25v10.5a2.25 2.25 0 01-2.25 2.25h-9.284c-.298 0-.585-.119-.796-.33z"/>
                                                            </svg>
                                                            <svg wire:loading wire:target="requestResetMonth({{ $gi }}, {{ $mi }})" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
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
                        wire:loading.attr="disabled" wire:target="closeModal"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1.5">
                        <svg wire:loading wire:target="closeModal" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                        </svg>
                        <span wire:loading.remove wire:target="closeModal">Batal</span>
                        <span wire:loading wire:target="closeModal">Menutup...</span>
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
