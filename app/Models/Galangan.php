<?php

namespace App\Models;

use App\Enums\GalanganStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Galangan extends Model
{
    use HasFactory, SoftDeletes;
    use HasUserStamps, LogsActivity;

    protected $table = 'galangan';

    protected $fillable = [
        'kode',
        'nama',
        'alamat',
        'kota',
        'provinsi',
        'latitude',
        'longitude',
        'telepon',
        'email',
        'pic_name',
        'pic_phone',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'status' => GalanganStatus::class,
        'latitude' => 'float',
        'longitude' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('galangan')
            ->logOnly(['kode', 'nama', 'alamat', 'kota', 'provinsi', 'latitude', 'longitude', 'pic_name', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

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

    public function hasLokasi(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
