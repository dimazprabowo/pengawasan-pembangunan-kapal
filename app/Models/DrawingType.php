<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DrawingType extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['nama'];

    public function drawings(): HasMany
    {
        return $this->hasMany(DrawingRegister::class);
    }

    public function label(): string
    {
        return $this->nama;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('drawing_type')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
