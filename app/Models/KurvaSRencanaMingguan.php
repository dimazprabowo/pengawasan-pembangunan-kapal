<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencana Kurva S per MINGGU — override distribusi mingguan yang diisi manual
 * (atau via "Bagi Rata" dari rencana bulanan). pct_rencana 3 desimal karena
 * merupakan hasil pembagian pct bulan ke minggu-minggunya.
 *
 * Semantik "mingguan menang": bulan yang memiliki data di tabel ini memakai
 * nilai mingguannya apa adanya; bulan tanpa data memakai bagi-rata bulanan.
 */
class KurvaSRencanaMingguan extends Model
{
    protected $table = 'kurva_s_rencana_mingguan';

    protected $fillable = [
        'work_group_id',
        'minggu_ke',
        'pct_rencana',
    ];

    protected $casts = [
        'minggu_ke' => 'integer',
        'pct_rencana' => 'float',
    ];

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(KurvaSWorkGroup::class, 'work_group_id');
    }
}
