<?php

namespace App\Models;

use App\Enums\MaterialStatus as DefaultStatus;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialStatus extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['nama'];

    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    public function label(): string
    {
        return DefaultStatus::tryFrom($this->nama)?->label() ?? $this->nama;
    }

    public function badgeClass(): string
    {
        return (DefaultStatus::tryFrom($this->nama) ?? DefaultStatus::Direncanakan)->badgeClass();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('material_status')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
