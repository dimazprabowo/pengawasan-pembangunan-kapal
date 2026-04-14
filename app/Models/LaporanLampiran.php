<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanLampiran extends Model
{
    use HasFactory;

    protected $table = 'laporan_lampiran';

    protected $fillable = [
        'laporan_harian_id',
        'file_path',
        'file_name',
        'file_size',
        'keterangan',
        'file_status',
        'file_error',
        'file_processed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'file_processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
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
        return !empty($this->file_path) && !empty($this->file_name);
    }

    public function isImage(): bool
    {
        if (!$this->file_name) {
            return false;
        }
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    }

    public function isPdf(): bool
    {
        if (!$this->file_name) {
            return false;
        }
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        return $extension === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    public function getFileExtensionAttribute(): ?string
    {
        if (!$this->file_name) {
            return null;
        }
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }
}
