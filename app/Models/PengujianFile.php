<?php

namespace App\Models;

use App\Traits\HasEncryptedRouteKey;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Lampiran pengujian (sertifikat, foto uji, dokumen hasil) — banyak file
 * gambar/PDF per Pengujian. Diproses async oleh ProcessPengujianFile; tidak
 * ikut tercetak di dokumen Word laporan.
 */
class PengujianFile extends Model
{
    use HasEncryptedRouteKey, HasFactory, HasUserStamps, LogsActivity;

    protected $table = 'pengujian_file';

    protected $fillable = [
        'pengujian_id',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'file_processed_at',
        'uploaded_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'file_processed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('pengujian_file')
            ->logOnly(['pengujian_id', 'file_name', 'file_status', 'file_path'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function pengujian(): BelongsTo
    {
        return $this->belongsTo(Pengujian::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Keterangan file = nama pengujian (untuk preview modal).
     */
    public function getKeteranganAttribute(): ?string
    {
        return $this->pengujian?->nama_pengujian;
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
