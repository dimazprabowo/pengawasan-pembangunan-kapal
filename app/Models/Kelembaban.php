<?php

namespace App\Models;

use App\Enums\KelembabanStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Kelembaban extends Model
{
    use HasFactory, SoftDeletes;
    use HasUserStamps;

    protected $table = 'kelembaban';

    protected $fillable = [
        'nama',
        'nilai',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'status' => KelembabanStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function laporanHarianPagi(): HasMany
    {
        return $this->hasMany(LaporanHarian::class, 'kelembaban_pagi_id');
    }

    public function laporanHarianSiang(): HasMany
    {
        return $this->hasMany(LaporanHarian::class, 'kelembaban_siang_id');
    }

    public function laporanHarianSore(): HasMany
    {
        return $this->hasMany(LaporanHarian::class, 'kelembaban_sore_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', KelembabanStatus::Active);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === KelembabanStatus::Active;
    }
}
