<?php

namespace App\Models;

use App\Enums\GalanganStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Galangan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'galangan';

    protected $fillable = [
        'kode',
        'nama',
        'alamat',
        'kota',
        'provinsi',
        'telepon',
        'email',
        'pic_name',
        'pic_phone',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'status' => GalanganStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function jenisKapal(): HasMany
    {
        return $this->hasMany(JenisKapal::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', GalanganStatus::Active);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === GalanganStatus::Active;
    }
}
