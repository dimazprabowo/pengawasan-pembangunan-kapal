<?php

namespace App\Livewire\Traits;

use App\Models\JenisKapal;
use Illuminate\Database\Eloquent\Collection;

trait HasJenisKapalFilter
{
    /**
     * Get filtered jenis kapal list based on user permissions.
     * 
     * @return Collection
     */
    protected function getJenisKapalList(): Collection
    {
        $canViewAllJenisKapal = auth()->user()->can('laporan_view_all_jenis_kapal');

        return JenisKapal::with(['company', 'galangan'])
            ->active()
            ->when(!$canViewAllJenisKapal, function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama')
            ->get();
    }

    /**
     * Check if user can view all jenis kapal.
     * 
     * @return bool
     */
    protected function canViewAllJenisKapal(): bool
    {
        return auth()->user()->can('laporan_view_all_jenis_kapal');
    }

    /**
     * Get the session key for storing selected jenis kapal ID.
     * 
     * @return string
     */
    protected function getJenisKapalSessionKey(): string
    {
        return 'laporan_jenis_kapal_id';
    }

    /**
     * Get the selected jenis kapal ID from session.
     * 
     * @return int|null
     */
    protected function getSelectedJenisKapalId(): ?int
    {
        return session($this->getJenisKapalSessionKey());
    }

    /**
     * Store the selected jenis kapal ID in session.
     * 
     * @param int|null $jenisKapalId
     * @return void
     */
    protected function setSelectedJenisKapalId(?int $jenisKapalId): void
    {
        session([$this->getJenisKapalSessionKey() => $jenisKapalId]);
    }
}
