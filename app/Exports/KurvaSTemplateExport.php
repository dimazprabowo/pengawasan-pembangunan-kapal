<?php

namespace App\Exports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class KurvaSTemplateExport implements WithMultipleSheets
{
    protected JenisKapal $jenisKapal;

    protected bool $withData;

    public function __construct(JenisKapal $jenisKapal, bool $withData = true)
    {
        $this->jenisKapal = $jenisKapal;
        $this->withData = $withData;
    }

    public function sheets(): array
    {
        $months = KurvaSRencanaSheet::resolveMonths($this->jenisKapal);

        return [
            new KurvaSInstructionsSheet($this->jenisKapal, $months),
            new KurvaSRencanaSheet($this->jenisKapal, $this->withData, $months),
        ];
    }
}
