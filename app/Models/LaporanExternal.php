<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanExternal extends Model
{
    use HasFactory;

    protected $table = 'laporan_external';

    protected $fillable = [
        'laporan_mingguan_id',
        'judul',
        'deskripsi',
        'file_path',
        'file_name',
        'file_size',
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

    public function laporanMingguan(): BelongsTo
    {
        return $this->belongsTo(LaporanMingguan::class);
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

    public function getFileExtensionAttribute(): ?string
    {
        if (!$this->file_name) {
            return null;
        }
        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }
}
