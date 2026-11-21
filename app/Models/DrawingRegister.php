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
 * Register gambar proyek dengan jenis bersama antar-kapal.
 * Revisi, tanggal, dan status disimpan pada riwayat terpisah
 * untuk tabel kumulatif sub-bab 2.2 laporan bulanan.
 */
class DrawingRegister extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'drawing_register';

    protected $fillable = ['data_kapal_id', 'drawing_type_id', 'nama'];

    public function type(): BelongsTo
    {
        return $this->belongsTo(DrawingType::class, 'drawing_type_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DrawingRevision::class)->orderBy('tanggal')->orderBy('revisi');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('drawing_register')
            ->logOnly(['data_kapal_id', 'drawing_type_id', 'nama'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
