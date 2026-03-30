<?php

namespace App\Models;

use App\Enums\CuacaStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cuaca extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cuaca';

    protected $fillable = [
        'nama',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'status' => CuacaStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function laporanPagi(): HasMany
    {
        return $this->hasMany(Laporan::class, 'cuaca_pagi_id');
    }

    public function laporanSiang(): HasMany
    {
        return $this->hasMany(Laporan::class, 'cuaca_siang_id');
    }

    public function laporanSore(): HasMany
    {
        return $this->hasMany(Laporan::class, 'cuaca_sore_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', CuacaStatus::Active);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === CuacaStatus::Active;
    }
}
