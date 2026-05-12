<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanMingguanProgress extends Model
{
    protected $table = 'laporan_mingguan_progress';

    protected $fillable = [
        'laporan_mingguan_id',
        'work_group_id',
        'pct_realisasi',
    ];

    protected $casts = [
        'pct_realisasi' => 'float',
    ];

    public function laporanMingguan(): BelongsTo
    {
        return $this->belongsTo(LaporanMingguan::class, 'laporan_mingguan_id');
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(KurvaSWorkGroup::class, 'work_group_id');
    }
}
