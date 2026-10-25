<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KurvaSWorkGroup extends Model
{
    protected $table = 'kurva_s_work_groups';

    protected $fillable = [
        'data_kapal_id',
        'nama',
        'bobot',
        'sort_order',
    ];

    protected $casts = [
        'bobot' => 'float',
        'sort_order' => 'integer',
    ];

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class, 'data_kapal_id');
    }

    public function kurvaSRencana(): HasMany
    {
        return $this->hasMany(KurvaSRencana::class, 'work_group_id')->orderBy('bulan_ke');
    }

    public function kurvaSRencanaMingguan(): HasMany
    {
        return $this->hasMany(KurvaSRencanaMingguan::class, 'work_group_id')->orderBy('minggu_ke');
    }

    public function laporanProgress(): HasMany
    {
        return $this->hasMany(LaporanMingguanProgress::class, 'work_group_id');
    }
}
