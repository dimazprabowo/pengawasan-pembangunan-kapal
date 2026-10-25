<?php

namespace App\Imports;

use App\Models\DataKapal;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class KurvaSTemplateImport implements WithMultipleSheets
{
    protected DataKapal $dataKapal;

    protected array $errors = [];

    protected array $rencanaData = [];

    public function __construct(DataKapal $dataKapal)
    {
        $this->dataKapal = $dataKapal;
    }

    public function sheets(): array
    {
        $rencanaImport = new KurvaSRencanaImport($this->dataKapal, $this);

        return [
            'Rencana' => $rencanaImport,
        ];
    }

    public function setRencanaData(array $data): void
    {
        $this->rencanaData = $data;
    }

    public function getRencanaData(): array
    {
        return $this->rencanaData;
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
