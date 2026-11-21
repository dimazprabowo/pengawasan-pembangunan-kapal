<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Catatan rapat proyek — jenis dinamis via katalog rapat_jenis (sub-bab 2.8 laporan bulanan).
 */
class Rapat extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'rapat';

    protected $fillable = [
        'data_kapal_id',
        'tanggal',
        'rapat_jenis_id',
        'lokasi',
        'topik',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rapat')
            ->logOnly(['data_kapal_id', 'tanggal', 'rapat_jenis_id', 'lokasi', 'topik'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function jenis(): BelongsTo
    {
        return $this->belongsTo(RapatJenis::class, 'rapat_jenis_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RapatFile::class)->oldest('id');
    }
}
