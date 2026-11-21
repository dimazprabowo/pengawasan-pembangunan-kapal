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
 * Pengadaan material & komponen proyek (sub-bab 2.5 laporan bulanan).
 */
class ProjectMaterial extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'project_material';

    protected $fillable = [
        'data_kapal_id',
        'material_category_id',
        'nama_item',
        'spesifikasi',
        'jumlah',
        'satuan',
        'material_status_id',
        'tanggal',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'material_category_id' => 'integer',
            'material_status_id' => 'integer',
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project_material')
            ->logOnly(['data_kapal_id', 'material_category_id', 'nama_item', 'material_status_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(MaterialStatus::class, 'material_status_id');
    }
}
