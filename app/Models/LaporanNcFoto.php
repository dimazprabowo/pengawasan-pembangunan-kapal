<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Foto bukti temuan NC (1:1 dengan LaporanNc).
 */
class LaporanNcFoto extends Model
{
    use HasEncryptedRouteKey, HasFactory;

    protected $table = 'laporan_nc_foto';

    protected $fillable = [
        'laporan_nc_id',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'file_processed_at',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'file_processed_at' => 'datetime',
    ];

    public function nc(): BelongsTo
    {
        return $this->belongsTo(LaporanNc::class, 'laporan_nc_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Keterangan foto = kendala NC (untuk preview modal).
     */
    public function getKeteranganAttribute(): ?string
    {
        return $this->nc?->kendala;
    }

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
        return ! empty($this->file_path) && ! empty($this->file_name);
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp']);
    }

    public function isPdf(): bool
    {
        return strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }
}
