<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DrawingRevision extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['revisi', 'tanggal', 'drawing_status_id'];

    protected function casts(): array
    {
        return ['revisi' => 'integer', 'tanggal' => 'date'];
    }

    public function drawing(): BelongsTo
    {
        return $this->belongsTo(DrawingRegister::class, 'drawing_register_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(DrawingStatus::class, 'drawing_status_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('drawing_revision')->logOnly(['drawing_register_id', 'revisi', 'tanggal', 'drawing_status_id'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
