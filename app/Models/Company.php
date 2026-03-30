<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'email',
        'phone',
        'address',
        'pic_name',
        'pic_email',
        'pic_phone',
        'status',
    ];

    protected $casts = [
        'status' => CompanyStatus::class,
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function jenisKapal(): HasMany
    {
        return $this->hasMany(JenisKapal::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', CompanyStatus::Active);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === CompanyStatus::Active;
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }
}
