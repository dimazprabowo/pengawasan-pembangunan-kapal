<?php

namespace App\Exports;

use App\Models\DataKapal;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class KurvaSTemplateExport implements WithMultipleSheets
{
    protected DataKapal $dataKapal;

    protected bool $withData;

    public function __construct(DataKapal $dataKapal, bool $withData = true)
    {
        $this->dataKapal = $dataKapal;
        $this->withData = $withData;
    }

    public function sheets(): array
    {
        $months = KurvaSRencanaSheet::resolveMonths($this->dataKapal);

        return [
            new KurvaSInstructionsSheet($this->dataKapal, $months),
            new KurvaSRencanaSheet($this->dataKapal, $this->withData, $months),
        ];
    }
}
