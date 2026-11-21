<?php

namespace App\Models;

use App\Enums\MaterialKategori;
use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MaterialCategory extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['nama'];

    public function materials(): HasMany
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    public function label(): string
    {
        return MaterialKategori::tryFrom($this->nama)?->label() ?? $this->nama;
    }

    public function badgeClass(): string
    {
        return (MaterialKategori::tryFrom($this->nama) ?? MaterialKategori::Lainnya)->badgeClass();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('material_category')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
