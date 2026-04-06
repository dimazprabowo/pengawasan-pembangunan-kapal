<?php

namespace App\Models;

use App\Enums\LaporanTipe;
use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Laporan extends Model
{
    use HasFactory, SoftDeletes, HasEncryptedRouteKey;

    protected $table = 'laporan';

    protected $fillable = [
        'user_id',
        'jenis_kapal_id',
        'tipe',
        'judul',
        'tanggal_laporan',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'job_id',
        'file_processed_at',
        'file_error',
        'suhu',
        'cuaca_pagi_id',
        'kelembaban_pagi_id',
        'cuaca_siang_id',
        'kelembaban_siang_id',
        'cuaca_sore_id',
        'kelembaban_sore_id',
    ];

    protected $casts = [
        'tipe' => LaporanTipe::class,
        'tanggal_laporan' => 'date',
        'suhu' => 'decimal:2',
        'file_processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jenisKapal(): BelongsTo
    {
        return $this->belongsTo(JenisKapal::class);
    }

    public function cuacaPagi(): BelongsTo
    {
        return $this->belongsTo(Cuaca::class, 'cuaca_pagi_id');
    }

    public function kelembabanPagi(): BelongsTo
    {
        return $this->belongsTo(Kelembaban::class, 'kelembaban_pagi_id');
    }

    public function cuacaSiang(): BelongsTo
    {
        return $this->belongsTo(Cuaca::class, 'cuaca_siang_id');
    }

    public function kelembabanSiang(): BelongsTo
    {
        return $this->belongsTo(Kelembaban::class, 'kelembaban_siang_id');
    }

    public function cuacaSore(): BelongsTo
    {
        return $this->belongsTo(Cuaca::class, 'cuaca_sore_id');
    }

    public function kelembabanSore(): BelongsTo
    {
        return $this->belongsTo(Kelembaban::class, 'kelembaban_sore_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(LaporanLampiran::class)->orderBy('created_at', 'asc');
    }

    public function personel(): HasMany
    {
        return $this->hasMany(LaporanPersonel::class)->orderBy('created_at', 'asc');
    }

    public function peralatan(): HasMany
    {
        return $this->hasMany(LaporanPeralatan::class)->orderBy('created_at', 'asc');
    }

    public function consumable(): HasMany
    {
        return $this->hasMany(LaporanConsumable::class)->orderBy('created_at', 'asc');
    }

    public function aktivitas(): HasMany
    {
        return $this->hasMany(LaporanAktivitas::class)->orderBy('created_at', 'asc');
    }

    // Scopes
    public function scopeTipe($query, string $tipe)
    {
        return $query->where('tipe', $tipe);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByJenisKapal($query, ?int $jenisKapalId)
    {
        if ($jenisKapalId) {
            return $query->where('jenis_kapal_id', $jenisKapalId);
        }
        return $query;
    }

    // File Status Helpers
    public function isFileProcessing(): bool
    {
        return in_array($this->file_status, ['pending', 'processing']);
    }

    public function isFileCompleted(): bool
    {
        return $this->file_status === 'completed';
    }

    public function isFileFailed(): bool
    {
        return $this->file_status === 'failed';
    }

    public function hasFile(): bool
    {
        return !empty($this->file_path) && !empty($this->file_name);
    }
}
