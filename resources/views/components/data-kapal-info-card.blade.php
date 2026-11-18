@props(['dataKapal'])

@if($dataKapal)
<div class="mb-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-medium text-blue-800 dark:text-blue-300">Informasi Kapal</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-sm font-semibold text-blue-900 dark:text-blue-100">{{ $dataKapal->nama }}</span>
            @if($dataKapal->kode)
                <span class="px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 font-mono text-xs font-semibold text-blue-800 dark:text-blue-200" title="Kode Proyek">{{ $dataKapal->kode }}</span>
            @endif
            @if($dataKapal->status)
                <span class="px-1.5 py-0.5 rounded text-xs font-semibold {{ $dataKapal->status->badgeClass() }}">{{ $dataKapal->status->label() }}</span>
            @endif
        </div>
    </div>
    <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-2">
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">Pekerjaan</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $dataKapal->nama_pekerjaan ?: '-' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">Pemberi Tugas</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $dataKapal->company?->name ?? '-' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">Galangan / Lokasi</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ trim(($dataKapal->galangan?->nama ?? '-').' '.($dataKapal->galangan?->kota ?? '')) }}</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">No. Kontrak</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $dataKapal->nomor_kontrak ?: '-' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">No. Pembangunan</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $dataKapal->nomor_pembangunan ?: '-' }}</dd>
        </div>
        <div>
            <dt class="text-[11px] uppercase tracking-wide text-blue-500/80 dark:text-blue-400/70">Periode Kontrak</dt>
            <dd class="text-sm font-medium text-blue-900 dark:text-blue-100">
                @if($dataKapal->hasKontrak())
                    {{ $dataKapal->tanggal_mulai_kontrak->translatedFormat('d M Y') }} – {{ $dataKapal->tanggal_selesai_kontrak->translatedFormat('d M Y') }}
                    <span class="text-xs font-normal text-blue-600 dark:text-blue-300">({{ $dataKapal->durasi_kontrak_hari }} hari)</span>
                @else
                    -
                @endif
            </dd>
        </div>
    </dl>
</div>
@endif
