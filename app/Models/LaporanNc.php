<?php

namespace App\Models;

use App\Enums\NcStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => NcStatus::class,
        'closed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('laporan_nc')
            ->logOnly(['kendala', 'tindakan', 'pic', 'kategori', 'status', 'closed_at', 'closed_by'])
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

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(LaporanNcFile::class, 'laporan_nc_file_link', 'laporan_nc_id', 'laporan_nc_file_id')
            ->withPivot('kind')
            ->withTimestamps();
    }

    public function beforeFiles(): BelongsToMany
    {
        return $this->files()->wherePivot('kind', 'before');
    }

    public function afterFiles(): BelongsToMany
    {
        return $this->files()->wherePivot('kind', 'after');
    }

    public function isOpen(): bool
    {
        return $this->status === NcStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === NcStatus::Closed;
    }
}
