<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KurvaSRencana extends Model
{
    protected $table = 'kurva_s_rencana';

    protected $fillable = [
        'work_group_id',
        'minggu_ke',
        'pct_rencana',
        'keterangan',
    ];

    protected $casts = [
        'minggu_ke'   => 'integer',
        'pct_rencana' => 'float',
    ];

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(KurvaSWorkGroup::class, 'work_group_id');
    }
}
