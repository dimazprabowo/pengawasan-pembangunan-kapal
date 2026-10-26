<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaporanMingguan extends Model
{
    use HasUserStamps;

    /** user_id sudah menjadi creator */
    protected array $userStamps = ['updated_by'];

    use HasEncryptedRouteKey, HasFactory, SoftDeletes;

    protected $table = 'laporan_mingguan';

    protected $fillable = [
        'user_id',
        'data_kapal_id',
        'tanggal_laporan',
        'periode_mulai',
        'periode_selesai',
        'ringkasan',
        'minggu_ke',
        'doc_path',
        'doc_name',
        'doc_status',
        'doc_generated_at',
        'doc_error',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date',
        'periode_mulai' => 'date',
        'periode_selesai' => 'date',
        'minggu_ke' => 'integer',
        'doc_generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Judul digenerate dari minggu ke- — tidak disimpan di database.
     */
    public function getJudulAttribute(): string
    {
        return $this->minggu_ke
            ? 'Laporan Mingguan Minggu ke-'.$this->minggu_ke
            : 'Laporan Mingguan';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function laporanProgress(): HasMany
    {
        return $this->hasMany(LaporanMingguanProgress::class, 'laporan_mingguan_id');
    }

    public function laporanHarian(): BelongsToMany
    {
        return $this->belongsToMany(LaporanHarian::class, 'laporan_mingguan_harian')
            ->orderBy('tanggal_laporan', 'asc');
    }

    public function lampiran(): BelongsToMany
    {
        return $this->belongsToMany(LaporanLampiran::class, 'laporan_mingguan_lampiran')
            ->orderBy('created_at', 'asc');
    }

    public function laporanExternal(): HasMany
    {
        return $this->hasMany(LaporanExternal::class, 'laporan_mingguan_id')
            ->orderBy('created_at', 'asc');
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDataKapal($query, ?int $dataKapalId)
    {
        if ($dataKapalId) {
            return $query->where('data_kapal_id', $dataKapalId);
        }

        return $query;
    }

    public function getLaporanHarianIdsAttribute(): array
    {
        return $this->laporanHarian->pluck('id')->toArray();
    }

    public function syncLaporanHarian(array $laporanHarianIds): void
    {
        $this->laporanHarian()->sync($laporanHarianIds);
    }

    public function getLampiranIdsAttribute(): array
    {
        return $this->lampiran->pluck('id')->toArray();
    }

    public function syncLampiran(array $lampiranIds): void
    {
        $this->lampiran()->sync($lampiranIds);
    }

    // Doc (Generated Word) Status Helpers
    public function isDocProcessing(): bool
    {
        return in_array($this->doc_status, ['pending', 'processing']);
    }

    public function isDocCompleted(): bool
    {
        return $this->doc_status === 'completed';
    }

    public function isDocFailed(): bool
    {
        return $this->doc_status === 'failed';
    }

    public function hasDoc(): bool
    {
        return ! empty($this->doc_path) && ! empty($this->doc_name);
    }
}
