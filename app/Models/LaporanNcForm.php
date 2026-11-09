<?php

namespace App\Models;

use App\Enums\NcFormJenis;
use App\Enums\NcFormStatus;
use App\Traits\HasEncryptedRouteKey;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Form NC (Non Conformance Report). Draft = digenerate dari NC & belum ada
 * scan TTD; setelah scan TTD diunggah form terkunci. Form penutupan yang
 * ditandatangani menutup semua NC di dalamnya.
 */
class LaporanNcForm extends Model
{
    use HasEncryptedRouteKey, HasFactory, HasUserStamps, LogsActivity;

    protected $table = 'laporan_nc_form';

    protected $fillable = [
        'data_kapal_id',
        'laporan_harian_id',
        'jenis',
        'nomor',
        'tanggal',
        'file_path',
        'file_name',
        'file_size',
        'file_status',
        'file_error',
        'file_processed_at',
        'signed_at',
        'signed_by',
    ];

    protected $casts = [
        'jenis' => NcFormJenis::class,
        'tanggal' => 'date',
        'file_size' => 'integer',
        'file_processed_at' => 'datetime',
        'signed_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('laporan_nc_form')
            ->logOnly(['jenis', 'nomor', 'tanggal', 'file_name', 'signed_at', 'signed_by'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function ncs(): BelongsToMany
    {
        return $this->belongsToMany(LaporanNc::class, 'laporan_nc_form_item', 'laporan_nc_form_id', 'laporan_nc_id')
            ->withTimestamps();
    }

    public function status(): NcFormStatus
    {
        return NcFormStatus::fromFileStatus($this->file_status);
    }

    public function isDraft(): bool
    {
        return $this->signed_at === null;
    }

    public function isTemuan(): bool
    {
        return $this->jenis === NcFormJenis::Temuan;
    }

    public function isPenutupan(): bool
    {
        return $this->jenis === NcFormJenis::Penutupan;
    }

    public function isFileProcessing(): bool
    {
        return in_array($this->file_status, ['pending', 'processing']);
    }

    public function isFileCompleted(): bool
    {
        return $this->file_status === 'completed';
    }

    public function hasFile(): bool
    {
        return ! empty($this->file_path) && ! empty($this->file_name);
    }

    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp']);
    }

    public function isPdf(): bool
    {
        return strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    public function isPreviewable(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    /**
     * Keterangan untuk preview modal.
     */
    public function getKeteranganAttribute(): string
    {
        return $this->jenis->label().' '.$this->nomor;
    }
}
