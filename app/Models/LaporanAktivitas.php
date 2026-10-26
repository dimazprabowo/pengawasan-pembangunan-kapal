<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LaporanAktivitas extends Model
{
    use HasFactory;

    protected $table = 'laporan_aktivitas';

    protected $fillable = [
        'laporan_harian_id',
        'kurva_s_work_group_id',
        'kategori',
        'aktivitas',
        'pic',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(KurvaSWorkGroup::class, 'kurva_s_work_group_id');
    }

    public function lampiran(): HasOne
    {
        return $this->hasOne(LaporanLampiran::class, 'laporan_aktivitas_id');
    }
}
