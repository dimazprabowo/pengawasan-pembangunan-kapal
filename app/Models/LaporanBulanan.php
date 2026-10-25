<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Laporan Bulanan - laporan narasi per bulan konstruksi.
 *
 * Data progress (rencana & realisasi) TIDAK disimpan di sini:
 * rencana diambil dari kurva_s_rencana (per bulan_ke) dan
 * realisasi dijumlahkan dari laporan_mingguan_progress milik
 * minggu-minggu dalam bulan ini (dihitung live oleh KurvaSService).
 */
class LaporanBulanan extends Model
{
    use HasEncryptedRouteKey, HasFactory, LogsActivity, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'laporan_bulanan';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'data_kapal_id',
        'tanggal_laporan',
        'periode_mulai',
        'periode_selesai',
        'ringkasan',
        'bulan_ke',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'tanggal_laporan' => 'date',
            'periode_mulai' => 'date',
            'periode_selesai' => 'date',
            'bulan_ke' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('laporan_bulanan')
            ->logOnly(['user_id', 'data_kapal_id', 'tanggal_laporan', 'periode_mulai', 'periode_selesai', 'bulan_ke'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the user who created this report.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the data kapal this report belongs to.
     */
    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    /**
     * Get the external reports attached to this monthly report.
     */
    public function laporanExternal(): HasMany
    {
        return $this->hasMany(LaporanExternal::class);
    }

    /**
     * Get the judul for this report.
     */
    public function getJudulAttribute(): string
    {
        return $this->bulan_ke ? "Laporan Bulanan Bulan ke-{$this->bulan_ke}" : 'Laporan Bulanan';
    }

    /**
     * Scope: filter by data kapal.
     */
    public function scopeByDataKapal($query, $dataKapalId)
    {
        return $query->where('data_kapal_id', $dataKapalId);
    }

    /**
     * Scope: filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
