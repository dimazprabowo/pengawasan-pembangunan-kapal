<?php

namespace App\Models;

use App\Traits\HasUserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class VendorLingkup extends Model
{
    use HasUserStamps, LogsActivity;

    protected $fillable = ['nama'];

    public function vendors(): HasMany
    {
        return $this->hasMany(ProjectVendor::class, 'lingkup_id');
    }

    public function label(): string
    {
        return $this->nama;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('vendor_lingkup')->logOnly(['nama'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
