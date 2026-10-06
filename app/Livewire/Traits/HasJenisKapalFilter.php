<?php

namespace App\Livewire\Traits;

use App\Models\JenisKapal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

trait HasJenisKapalFilter
{
    /**
     * Base query for jenis kapal accessible by the current user.
     */
    protected function getJenisKapalQuery(): Builder
    {
        return JenisKapal::with(['company', 'galangan'])
            ->active()
            ->when(! $this->canViewAllJenisKapal(), function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama');
    }

    /**
     * Get filtered jenis kapal list based on user permissions.
     */
    protected function getJenisKapalList(): Collection
    {
        return $this->getJenisKapalQuery()->get();
    }

    /**
     * Check if user can view all jenis kapal.
     */
    protected function canViewAllJenisKapal(): bool
    {
        return auth()->user()->can('laporan_view_all_jenis_kapal');
    }

    /**
     * Check if user may access a specific jenis kapal (company scope).
     */
    protected function canAccessJenisKapal(JenisKapal $jenisKapal): bool
    {
        if ($this->canViewAllJenisKapal()) {
            return true;
        }

        return $jenisKapal->company_id === auth()->user()->company_id;
    }

    /**
     * Abort 403 unless user may access the given jenis kapal.
     */
    protected function authorizeJenisKapalAccess(JenisKapal $jenisKapal): void
    {
        abort_unless($this->canAccessJenisKapal($jenisKapal), 403);
    }
}
