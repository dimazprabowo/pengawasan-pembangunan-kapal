<?php

namespace App\Livewire\Traits;

use App\Models\DataKapal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

trait HasDataKapalFilter
{
    /**
     * Base query for data kapal accessible by the current user.
     */
    protected function getDataKapalQuery(): Builder
    {
        return DataKapal::with(['company', 'galangan'])
            ->active()
            ->when(! $this->canViewAllDataKapal(), function ($q) {
                $q->whereHas('company', function ($q) {
                    $q->where('id', auth()->user()->company_id);
                });
            })
            ->orderBy('nama');
    }

    /**
     * Get filtered data kapal list based on user permissions.
     */
    protected function getDataKapalList(): Collection
    {
        return $this->getDataKapalQuery()->get();
    }

    /**
     * Check if user can view all data kapal.
     */
    protected function canViewAllDataKapal(): bool
    {
        return auth()->user()->can('laporan_view_all_data_kapal');
    }

    /**
     * Check if user may access a specific data kapal (company scope).
     */
    protected function canAccessDataKapal(DataKapal $dataKapal): bool
    {
        if ($this->canViewAllDataKapal()) {
            return true;
        }

        return $dataKapal->company_id === auth()->user()->company_id;
    }

    /**
     * Abort 403 unless user may access the given data kapal.
     */
    protected function authorizeDataKapalAccess(DataKapal $dataKapal): void
    {
        abort_unless($this->canAccessDataKapal($dataKapal), 403);
    }
}
