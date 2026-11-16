<?php

namespace App\Models;

use App\Enums\RapatJenis;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Catatan rapat proyek — mingguan/bulanan/lainnya (BAB 3.1 laporan bulanan).
 */
class Rapat extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'rapat';

    protected $fillable = [
        'data_kapal_id',
        'tanggal',
        'jenis',
        'lokasi',
        'topik',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jenis' => RapatJenis::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('rapat')
            ->logOnly(['data_kapal_id', 'tanggal', 'jenis', 'lokasi', 'topik'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
