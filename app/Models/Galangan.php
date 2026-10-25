<?php

namespace App\Models;

use App\Enums\GalanganStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function dataKapal(): HasMany
    {
        return $this->hasMany(DataKapal::class);
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
