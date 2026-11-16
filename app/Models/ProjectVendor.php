<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Daftar sub-contractor / vendor proyek (BAB 2.4 laporan bulanan).
 */
class ProjectVendor extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'project_vendor';

    protected $fillable = [
        'data_kapal_id',
        'nama',
        'lingkup_pekerjaan',
        'kontak',
        'keterangan',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project_vendor')
            ->logOnly(['data_kapal_id', 'nama', 'lingkup_pekerjaan'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
