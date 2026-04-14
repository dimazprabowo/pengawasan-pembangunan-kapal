<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanMingguan extends Model
{
    use HasFactory, SoftDeletes, HasEncryptedRouteKey;

    protected $table = 'laporan_mingguan';

    protected $fillable = [
        'user_id',
        'jenis_kapal_id',
        'judul',
        'tanggal_laporan',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jenisKapal(): BelongsTo
    {
        return $this->belongsTo(JenisKapal::class);
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
}
