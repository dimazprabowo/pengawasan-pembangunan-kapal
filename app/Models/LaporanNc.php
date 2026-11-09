<?php

namespace App\Models;

use App\Enums\NcStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LaporanNc extends Model
{
    use HasFactory, HasUserStamps, LogsActivity;

    protected $table = 'laporan_nc';

    protected $fillable = [
        'laporan_harian_id',
        'data_kapal_id',
        'kurva_s_work_group_id',
        'kategori',
        'nomor',
        'kendala',
        'tindakan',
        'pic',
        'status',
        'closed_at',
        'closed_by',
        'reopened_at',
        'reopened_by',
        'reopen_note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => NcStatus::class,
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('laporan_nc')
            ->logOnly(['kendala', 'tindakan', 'pic', 'kategori', 'status', 'closed_at', 'closed_by', 'reopened_at', 'reopened_by', 'reopen_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(KurvaSWorkGroup::class, 'kurva_s_work_group_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }

    /**
     * Foto bukti temuan (1 foto per NC).
     */
    public function foto(): HasOne
    {
        return $this->hasOne(LaporanNcFoto::class, 'laporan_nc_id');
    }

    /**
     * Form NC (temuan/penutupan) yang memuat NC ini.
     */
    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(LaporanNcForm::class, 'laporan_nc_form_item', 'laporan_nc_id', 'laporan_nc_form_id')
            ->withTimestamps();
    }

    public function isOpen(): bool
    {
        return $this->status === NcStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === NcStatus::Closed;
    }

    /**
     * NC sudah masuk draft Form Penutupan yang belum ditandatangani —
     * tidak bisa dipindah ke draft penutupan lain sebelum draft itu dihapus.
     */
    public function isInDraftPenutupan(): bool
    {
        return $this->forms->contains(fn (LaporanNcForm $f) => $f->isPenutupan() && $f->isDraft());
    }
}
