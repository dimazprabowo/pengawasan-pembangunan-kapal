<?php

namespace App\Models;

use App\Enums\DrawingStatus as DefaultStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DrawingStatus extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['nama'];

    public function revisions(): HasMany
    {
        return $this->hasMany(DrawingRevision::class);
    }

    public function label(): string
    {
        return DefaultStatus::tryFrom($this->nama)?->label() ?? $this->nama;
    }

    public function badgeClass(): string
    {
        return (DefaultStatus::tryFrom($this->nama) ?? DefaultStatus::InProgress)->badgeClass();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('drawing_status')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
