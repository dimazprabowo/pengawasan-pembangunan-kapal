{{-- Aksi dokumen Form NC: unduh docx, preview scan, upload/ganti scan, hapus draft.
     Dipakai di chip header grup (Form Temuan) dan sel Form Close (Form Penutupan). --}}
@php
    $ncForm = $ncForm ?? null;
    $iconSize = $iconSize ?? 'w-4 h-4';
    $gap = $gap ?? 'gap-2';
@endphp
@if($ncForm)
<div class="flex items-center {{ $gap }}">
    @can('create', \App\Models\LaporanNcForm::class)
        <button type="button" wire:click="downloadForm({{ $ncForm->id }})" wire:loading.attr="disabled" wire:target="downloadForm({{ $ncForm->id }})"
            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 disabled:opacity-50" title="Unduh dokumen Word {{ $ncForm->nomor }}">
            <svg wire:loading.class="hidden" wire:target="downloadForm({{ $ncForm->id }})" class="{{ $iconSize }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <svg wire:loading wire:target="downloadForm({{ $ncForm->id }})" class="animate-spin {{ $iconSize }}" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </button>
    @endcan
    @if($ncForm->isFileCompleted())
        @can('lampiranPreview', \App\Models\LaporanHarian::class)
            <button type="button" wire:click="previewForm({{ $ncForm->id }})" wire:loading.attr="disabled" wire:target="previewForm({{ $ncForm->id }})"
                class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 disabled:opacity-50" title="Preview scan bertanda tangan">
                <svg wire:loading.class="hidden" wire:target="previewForm({{ $ncForm->id }})" class="{{ $iconSize }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <svg wire:loading wire:target="previewForm({{ $ncForm->id }})" class="animate-spin {{ $iconSize }}" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </button>
        @endcan
    @endif
    @can('upload', $ncForm)
        <button type="button" wire:click="openUploadModal({{ $ncForm->id }})" wire:loading.attr="disabled" wire:target="openUploadModal({{ $ncForm->id }})"
            class="{{ $ncForm->isDraft() ? 'text-emerald-600 hover:text-emerald-800 dark:text-emerald-400' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400' }} disabled:opacity-50"
            title="{{ $ncForm->isDraft() ? 'Upload scan TTD' : 'Ganti scan TTD' }}">
            <svg wire:loading.class="hidden" wire:target="openUploadModal({{ $ncForm->id }})" class="{{ $iconSize }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            <svg wire:loading wire:target="openUploadModal({{ $ncForm->id }})" class="animate-spin {{ $iconSize }}" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </button>
    @endcan
    {{-- isDraft dicek eksplisit: Gate::before super admin mem-bypass cek state di Policy --}}
    @if($ncForm->isDraft())
        @can('delete', $ncForm)
            <button type="button" wire:click="confirmDeleteForm({{ $ncForm->id }})" wire:loading.attr="disabled" wire:target="confirmDeleteForm({{ $ncForm->id }})"
                class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 disabled:opacity-50" title="Hapus draft {{ $ncForm->nomor }}">
                <svg wire:loading.class="hidden" wire:target="confirmDeleteForm({{ $ncForm->id }})" class="{{ $iconSize }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <svg wire:loading wire:target="confirmDeleteForm({{ $ncForm->id }})" class="animate-spin {{ $iconSize }}" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            </button>
        @endcan
    @endif
</div>
@endif
