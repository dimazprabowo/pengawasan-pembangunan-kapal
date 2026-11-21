<?php

namespace App\Models;

use App\Enums\OwnerStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Owner extends Model
{
    use HasFactory, SoftDeletes;
    use HasUserStamps;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'pic_name',
        'pic_email',
        'pic_phone',
        'npwp',
        'status',
    ];

    protected $casts = [
        'status' => OwnerStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function dataKapal(): HasMany
    {
        return $this->hasMany(DataKapal::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', OwnerStatus::Active);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === OwnerStatus::Active;
    }

    public function getNpwpFormattedAttribute(): ?string
    {
        if (! $this->npwp) {
            return null;
        }

        $npwp = preg_replace('/\D/', '', $this->npwp);
        $length = strlen($npwp);

        if ($length === 15) {
            return substr($npwp, 0, 2).'.'.
                   substr($npwp, 2, 3).'.'.
                   substr($npwp, 5, 3).'.'.
                   substr($npwp, 8, 1).'-'.
                   substr($npwp, 9, 3).'.'.
                   substr($npwp, 12, 3);
        } elseif ($length === 16) {
            return substr($npwp, 0, 4).'.'.
                   substr($npwp, 4, 4).'.'.
                   substr($npwp, 8, 4).'.'.
                   substr($npwp, 12, 4);
        }

        return $npwp;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
