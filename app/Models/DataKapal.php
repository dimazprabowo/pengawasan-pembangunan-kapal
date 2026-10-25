<?php

namespace App\Models;

use App\Enums\DataKapalStatus;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataKapal extends Model
{
    use HasEncryptedRouteKey, HasFactory, SoftDeletes;

    protected $table = 'data_kapal';

    protected $fillable = [
        'company_id',
        'galangan_id',
        'nama',
        'deskripsi',
        'spesifikasi',
        'tanggal_mulai_kontrak',
        'durasi_kontrak_hari',
        'minggu_bulan_map',
        'template_path_harian',
        'template_path_mingguan',
        'template_path_bulanan',
        'status',
    ];

    protected $casts = [
        'status' => DataKapalStatus::class,
        'spesifikasi' => 'array',
        'tanggal_mulai_kontrak' => 'date',
        'durasi_kontrak_hari' => 'integer',
        'minggu_bulan_map' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function galangan(): BelongsTo
    {
        return $this->belongsTo(Galangan::class);
    }

    public function laporanHarian(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function laporanMingguan(): HasMany
    {
        return $this->hasMany(LaporanMingguan::class);
    }

    public function laporanBulanan(): HasMany
    {
        return $this->hasMany(LaporanBulanan::class);
    }

    public function kurvaSWorkGroups(): HasMany
    {
        return $this->hasMany(KurvaSWorkGroup::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('status', DataKapalStatus::Active);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === DataKapalStatus::Active;
    }

    /**
     * Kontrak dianggap terisi lengkap jika tanggal mulai & durasi hari ada.
     */
    public function hasKontrak(): bool
    {
        return ! empty($this->tanggal_mulai_kontrak) && ! empty($this->durasi_kontrak_hari);
    }

    /**
     * Tanggal selesai kontrak = tanggal mulai + durasi hari - 1 (hari kalender, inklusif).
     */
    public function getTanggalSelesaiKontrakAttribute(): ?\Carbon\Carbon
    {
        if (! $this->hasKontrak()) {
            return null;
        }

        return $this->tanggal_mulai_kontrak->copy()->addDays($this->durasi_kontrak_hari - 1);
    }

    public function hasTemplate(string $tipe): bool
    {
        $column = 'template_path_'.$tipe;
        $hasPath = ! empty($this->$column);
        $fileExists = $hasPath ? \Storage::disk('local')->exists($this->$column) : false;

        return $hasPath && $fileExists;
    }

    public function getTemplateFullPath(string $tipe): ?string
    {
        if (! $this->hasTemplate($tipe)) {
            return null;
        }

        $column = 'template_path_'.$tipe;

        return \Storage::disk('local')->path($this->$column);
    }

    public function hasAnyTemplate(): bool
    {
        return $this->hasTemplate('harian') || $this->hasTemplate('mingguan') || $this->hasTemplate('bulanan');
    }

    public function getLaporanCount(string $tipe): int
    {
        return match ($tipe) {
            'harian' => $this->laporan_harian_count ?? $this->laporanHarian()->count(),
            'mingguan' => $this->laporan_mingguan_count ?? $this->laporanMingguan()->count(),
            'bulanan' => $this->laporan_bulanan_count ?? $this->laporanBulanan()->count(),
            default => 0,
        };
    }
}
