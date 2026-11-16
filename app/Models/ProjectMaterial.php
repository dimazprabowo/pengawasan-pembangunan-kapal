<?php

namespace App\Models;

use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pengadaan material & komponen proyek (BAB 2.5 laporan bulanan).
 */
class ProjectMaterial extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'project_material';

    protected $fillable = [
        'data_kapal_id',
        'kategori',
        'nama_item',
        'spesifikasi',
        'jumlah',
        'satuan',
        'status',
        'tanggal',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => MaterialKategori::class,
            'status' => MaterialStatus::class,
            'jumlah' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('project_material')
            ->logOnly(['data_kapal_id', 'kategori', 'nama_item', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
