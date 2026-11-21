<?php

namespace App\Models;

use App\Enums\RapatJenis as DefaultJenis;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RapatJenis extends Model
{
    use HasUserStamps, LogsActivity;

    protected $table = 'rapat_jenis';

    protected $fillable = ['nama'];

    public function rapats(): HasMany
    {
        return $this->hasMany(Rapat::class);
    }

    public function label(): string
    {
        return DefaultJenis::tryFrom($this->nama)?->label() ?? $this->nama;
    }

    public function badgeClass(): string
    {
        return (DefaultJenis::tryFrom($this->nama) ?? DefaultJenis::Lainnya)->badgeClass();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('rapat_jenis')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
