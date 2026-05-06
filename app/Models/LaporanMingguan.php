<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LaporanMingguan extends Model
{
    use HasFactory, SoftDeletes, HasEncryptedRouteKey;

    protected $table = 'laporan_mingguan';

    protected $fillable = [
        'user_id',
        'jenis_kapal_id',
        'judul',
        'tanggal_laporan',
        'periode_mulai',
        'periode_selesai',
        'ringkasan',
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
        'doc_generated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jenisKapal(): BelongsTo
    {
        return $this->belongsTo(JenisKapal::class);
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

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByJenisKapal($query, ?int $jenisKapalId)
    {
        if ($jenisKapalId) {
            return $query->where('jenis_kapal_id', $jenisKapalId);
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
        return !empty($this->doc_path) && !empty($this->doc_name);
    }
}
