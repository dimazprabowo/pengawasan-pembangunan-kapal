<?php

namespace App\Models;

use App\Enums\JenisKapalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JenisKapal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'jenis_kapal';

    protected $fillable = [
        'company_id',
        'galangan_id',
        'nama',
        'deskripsi',
        'template_path_harian',
        'template_path_mingguan',
        'template_path_bulanan',
        'status',
    ];

    protected $casts = [
        'status' => JenisKapalStatus::class,
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

    public function scopeActive($query)
    {
        return $query->where('status', JenisKapalStatus::Active);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === JenisKapalStatus::Active;
    }

    public function hasTemplate(string $tipe): bool
    {
        $column = 'template_path_' . $tipe;
        $hasPath = !empty($this->$column);
        $fileExists = $hasPath ? \Storage::disk('local')->exists($this->$column) : false;
        
        return $hasPath && $fileExists;
    }

    public function getTemplateFullPath(string $tipe): ?string
    {
        if (!$this->hasTemplate($tipe)) {
            return null;
        }

        $column = 'template_path_' . $tipe;
        return \Storage::disk('local')->path($this->$column);
    }

    public function hasAnyTemplate(): bool
    {
        return $this->hasTemplate('harian') || $this->hasTemplate('mingguan') || $this->hasTemplate('bulanan');
    }

    public function getLaporanCount(string $tipe): int
    {
        // Currently all reports are stored in laporan_harian table
        // TODO: Add tipe field to laporan_harian table to distinguish between harian, mingguan, bulanan
        if ($tipe === 'harian') {
            return $this->laporan_harian_count ?? 0;
        }
        return 0;
    }
}
