<?php

namespace App\Models;

use App\Enums\DrawingStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Register gambar (drawing) proyek — status per tahap:
 * produksi galangan → review MNO → approval Klas, beserta
 * hitungan comment per tahap untuk agregasi BAB 2.2 laporan bulanan.
 */
class DrawingRegister extends Model
{
    use HasFactory, HasUserStamps, LogsActivity, SoftDeletes;

    protected $table = 'drawing_register';

    protected $fillable = [
        'data_kapal_id',
        'nomor',
        'judul',
        'status_produksi',
        'status_review_mno',
        'status_approval_klas',
        'mno_comment_open',
        'mno_comment_closed',
        'mno_comment_informasi',
        'klas_comment_open',
        'klas_comment_closed',
        'klas_comment_informasi',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'status_produksi' => DrawingStatus::class,
            'status_review_mno' => DrawingStatus::class,
            'status_approval_klas' => DrawingStatus::class,
            'mno_comment_open' => 'integer',
            'mno_comment_closed' => 'integer',
            'mno_comment_informasi' => 'integer',
            'klas_comment_open' => 'integer',
            'klas_comment_closed' => 'integer',
            'klas_comment_informasi' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('drawing_register')
            ->logOnly(['data_kapal_id', 'nomor', 'judul', 'status_produksi', 'status_review_mno', 'status_approval_klas'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function dataKapal(): BelongsTo
    {
        return $this->belongsTo(DataKapal::class);
    }
}
