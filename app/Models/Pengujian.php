<?php

namespace App\Models;

use App\Enums\PengujianStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Jadwal & hasil pengujian pembangunan kapal (BAB 2.7 laporan bulanan).
 */
class Pengujian extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'pengujian';

    protected $fillable = [
        'data_kapal_id',
        'nama_pengujian',
        'tanggal',
        'status',
        'hasil',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'status' => PengujianStatus::class,
            'tanggal' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pengujian')
            ->logOnly(['data_kapal_id', 'nama_pengujian', 'tanggal', 'status', 'hasil'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
