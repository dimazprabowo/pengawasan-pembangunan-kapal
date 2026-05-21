<?php

namespace App\Imports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class KurvaSTemplateImport implements WithMultipleSheets
{
    protected JenisKapal $jenisKapal;
    protected array $errors = [];

    public function __construct(JenisKapal $jenisKapal)
    {
        $this->jenisKapal = $jenisKapal;
    }

    public function sheets(): array
    {
        $rencanaImport = new KurvaSRencanaImport($this->jenisKapal, $this);

        return [
            'Rencana' => $rencanaImport,
        ];
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
