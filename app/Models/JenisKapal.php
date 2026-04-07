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
        'template_path',
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

    public function laporan(): HasMany
    {
        return $this->hasMany(Laporan::class);
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

    public function hasTemplate(): bool
    {
        $hasPath = !empty($this->template_path);
        $fileExists = $hasPath ? \Storage::disk('local')->exists($this->template_path) : false;
        
        return $hasPath && $fileExists;
    }

    public function getTemplateFullPath(): ?string
    {
        if (!$this->hasTemplate()) {
            return null;
        }

        $fullPath = \Storage::disk('local')->path($this->template_path);
        
        return $fullPath;
    }
}
