<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LaporanNcFile extends Model
{
    use HasEncryptedRouteKey, HasFactory;

    protected $table = 'laporan_nc_file';

    protected $fillable = [
        'data_kapal_id',
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ncs(): BelongsToMany
    {
        return $this->belongsToMany(LaporanNc::class, 'laporan_nc_file_link', 'laporan_nc_file_id', 'laporan_nc_id')
            ->withPivot('kind')
            ->withTimestamps();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    /**
     * Keterangan file = kendala NC pertama yang menautkannya (untuk preview modal).
     */
    public function getKeteranganAttribute(): ?string
    {
        return $this->ncs->first()?->kendala;
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
        if (! $this->file_name) {
            return false;
        }
        $extension = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg']);
    }

    public function isPdf(): bool
    {
        if (! $this->file_name) {
            return false;
        }

        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }
}
