{{-- 3.1 Problem/Rekomendasi (dipakai Create & Edit). Sub-bab 2.5 Realisasi Progress
    = tarikan ringkasan laporan mingguan, bukan input. --}}
<div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-5">
    <div class="flex flex-col sm:flex-row items-start sm:justify-between gap-3 mb-3">
        <div class="min-w-0 w-full sm:w-auto sm:flex-1">
            <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Problem &amp; Rekomendasi</h4>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Otomatis dari daftar Non-Conformance (NC) — NC Open terbawa, Closed tampil pada periode ia ditutup. Tampil di sub-bab 3.1 laporan Word.</p>
        </div>
        @if($ncRekap->isNotEmpty())
            <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto sm:shrink-0">
                <span class="inline-block px-2 py-0.5 text-xs font-semibold text-center rounded-full bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300">{{ $ncRekap->count() }} NC</span>
                <span class="inline-block px-2 py-0.5 text-xs font-semibold text-center rounded-full bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ $ncRekap->where('status', \App\Enums\NcStatus::Open)->count() }} Open</span>
                <span class="inline-block px-2 py-0.5 text-xs font-semibold text-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ $ncRekap->where('status', \App\Enums\NcStatus::Closed)->count() }} Closed</span>
            </div>
        @endif
    </div>

    @if(! $periode_mulai || ! $periode_selesai)
        <div class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40 px-4 py-6 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">Pilih <span class="font-medium">bulan ke-</span> terlebih dahulu untuk melihat rekap NC periode ini.</p>
        </div>
    @elseif($ncRekap->isEmpty())
        <div class="rounded-lg border border-dashed border-emerald-300 dark:border-emerald-700 bg-emerald-50/50 dark:bg-emerald-900/10 px-4 py-6 text-center">
            <svg class="mx-auto h-8 w-8 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <p class="mt-1.5 text-sm text-emerald-700 dark:text-emerald-400">Tidak ada NC pada periode ini — bagian ini tidak akan diisi di dokumen Word.</p>
        </div>
    @else
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-200 dark:divide-gray-700 overflow-hidden">
            @foreach($ncRekap as $nc)
                <div class="flex gap-3 px-4 py-3 {{ $nc->status === \App\Enums\NcStatus::Open ? 'bg-red-50/40 dark:bg-red-900/10' : 'bg-white dark:bg-gray-800' }}">
                    <div class="w-1 self-stretch rounded-full flex-shrink-0 {{ $nc->status === \App\Enums\NcStatus::Open ? 'bg-red-400 dark:bg-red-500' : 'bg-emerald-400 dark:bg-emerald-500' }}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-mono font-semibold text-gray-700 dark:text-gray-300">{{ $nc->nomor ?? 'NC' }}</span>
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $nc->laporanHarian?->tanggal_laporan?->translatedFormat('d M Y') }}</span>
                            @if($nc->kategori)
                                <span class="text-xs text-gray-400 dark:text-gray-500">· {{ $nc->kategori }}</span>
                            @endif
                            <span class="inline-block ml-auto px-2 py-0.5 text-xs font-medium rounded-full {{ $nc->status->badgeClass() }}">{{ $nc->status->label() }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Problem</span>
                            {{ $nc->kendala ?? '-' }}
                        </p>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Rekomendasi</span>
                            {{ $nc->tindakan ?: '—' }}
                        </p>
                        @if($nc->status === \App\Enums\NcStatus::Closed && $nc->closed_at)
                            <p class="mt-0.5 text-xs text-emerald-600 dark:text-emerald-400">
                                Ditutup {{ $nc->closed_at->translatedFormat('d M Y') }}{{ $nc->closedBy ? ' oleh '.$nc->closedBy->name : '' }}
                            </p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
