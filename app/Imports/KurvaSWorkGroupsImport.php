<?php

namespace App\Imports;

use App\Models\JenisKapal;
use App\Models\KurvaSWorkGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KurvaSWorkGroupsImport implements ToCollection, WithHeadingRow
{
    protected JenisKapal $jenisKapal;
    protected KurvaSTemplateImport $parent;
    public array $workGroupsData = [];

    public function __construct(JenisKapal $jenisKapal, KurvaSTemplateImport $parent)
    {
        $this->jenisKapal = $jenisKapal;
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        $rows = $rows->filter(function ($row) {
            return !empty($row['nama_work_group']);
        });

        if ($rows->isEmpty()) {
            $this->parent->addError('Sheet Work Groups: Tidak ada data work group yang valid.');
            return;
        }

        if ($rows->count() > 20) {
            $this->parent->addError('Sheet Work Groups: Maksimal 20 work groups. Ditemukan: ' . $rows->count());
            return;
        }

        $totalBobot = 0;
        $sortOrder = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;

            $validator = Validator::make($row->toArray(), [
                'nama_work_group' => 'required|string|max:255',
                'bobot' => 'required|numeric|min:0.01|max:100',
            ], [
                'nama_work_group.required' => "Baris {$rowNumber}: Nama work group wajib diisi",
                'nama_work_group.max' => "Baris {$rowNumber}: Nama work group maksimal 255 karakter",
                'bobot.required' => "Baris {$rowNumber}: Bobot wajib diisi",
                'bobot.numeric' => "Baris {$rowNumber}: Bobot harus berupa angka",
                'bobot.min' => "Baris {$rowNumber}: Bobot minimal 0.01",
                'bobot.max' => "Baris {$rowNumber}: Bobot maksimal 100",
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $error) {
                    $this->parent->addError('Sheet Work Groups: ' . $error);
                }
                continue;
            }

            $bobot = (float) $row['bobot'];
            $totalBobot += $bobot;

            $this->workGroupsData[] = [
                'nama' => trim($row['nama_work_group']),
                'bobot' => $bobot,
                'sort_order' => $sortOrder++,
            ];
        }

        if (abs($totalBobot - 100.00) > 0.01) {
            $this->parent->addError(
                sprintf(
                    'Sheet Work Groups: Total bobot harus = 100.00%%. Total saat ini: %.2f%%',
                    $totalBobot
                )
            );
        }
    }

    public function getWorkGroupsData(): array
    {
        return $this->workGroupsData;
    }
}
