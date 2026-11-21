<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jadwal & hasil pengujian pembangunan kapal (sub-bab 2.7 laporan bulanan).
 */
class Pengujian extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'pengujian';

    protected $fillable = [
        'data_kapal_id',
        'nama_pengujian',
        'tanggal',
        'pengujian_status_id',
        'hasil',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'pengujian_status_id' => 'integer',
            'tanggal' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pengujian')
            ->logOnly(['data_kapal_id', 'nama_pengujian', 'tanggal', 'pengujian_status_id', 'hasil'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(PengujianStatus::class, 'pengujian_status_id');
    }
}
