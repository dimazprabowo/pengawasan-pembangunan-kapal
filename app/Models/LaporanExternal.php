<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaporanExternal extends Model
{
    use HasFactory, HasUserStamps, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('laporan_external')
            ->logOnly(['laporan_harian_id', 'laporan_mingguan_id', 'laporan_bulanan_id', 'judul', 'deskripsi', 'file_path', 'file_status'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $table = 'laporan_external';

    protected $fillable = [
        'laporan_harian_id',
        'laporan_mingguan_id',
        'laporan_bulanan_id',
        'judul',
        'deskripsi',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'rendered_pages',
        'file_processed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'rendered_pages' => 'array',
        'file_processed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function laporanMingguan(): BelongsTo
    {
        return $this->belongsTo(LaporanMingguan::class);
    }

    public function laporanBulanan(): BelongsTo
    {
        return $this->belongsTo(LaporanBulanan::class);
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

    public function getFileExtensionAttribute(): ?string
    {
        if (! $this->file_name) {
            return null;
        }

        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return in_array($this->file_extension ?? '', ['jpg', 'jpeg', 'png', 'webp']);
    }

    public function isPdf(): bool
    {
        return $this->file_extension === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }
}
