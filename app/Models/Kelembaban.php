<?php

namespace App\Models;

use App\Enums\KelembabanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelembaban extends Model
{
    use HasFactory, SoftDeletes;

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

    public function laporanPagi(): HasMany
    {
        return $this->hasMany(Laporan::class, 'kelembaban_pagi_id');
    }

    public function laporanSiang(): HasMany
    {
        return $this->hasMany(Laporan::class, 'kelembaban_siang_id');
    }

    public function laporanSore(): HasMany
    {
        return $this->hasMany(Laporan::class, 'kelembaban_sore_id');
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
