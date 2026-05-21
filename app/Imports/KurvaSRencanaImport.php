<?php

namespace App\Imports;

use App\Models\JenisKapal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KurvaSRencanaImport implements ToCollection, WithHeadingRow
{
    protected JenisKapal $jenisKapal;
    protected KurvaSTemplateImport $parent;
    public array $rencanaData = [];

    public function __construct(JenisKapal $jenisKapal, KurvaSTemplateImport $parent)
    {
        $this->jenisKapal = $jenisKapal;
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        $rows = $rows->filter(function ($row) {
            return !empty($row['work_group']);
        });

        if ($rows->isEmpty()) {
            $this->parent->addError('Sheet Rencana: Tidak ada data yang valid.');
            return;
        }

        $totalBobot = 0;
        $workGroupsData = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            
            $no = $row['no'] ?? null;
            $workGroupName = trim($row['work_group'] ?? '');
            $bobot = $row['bobot'] ?? null;

            if (empty($workGroupName)) {
                $this->parent->addError("Baris {$rowNumber}: Work group wajib diisi");
                continue;
            }

            // Skip TOTAL row
            if (strtoupper($workGroupName) === 'TOTAL') {
                continue;
            }

            if (!is_numeric($bobot)) {
                $this->parent->addError("Baris {$rowNumber}: Bobot harus berupa angka untuk work group \"{$workGroupName}\"");
                continue;
            }

            $bobot = (float) $bobot;
            if ($bobot < 0.01 || $bobot > 100) {
                $this->parent->addError("Baris {$rowNumber}: Bobot harus antara 0.01-100 untuk work group \"{$workGroupName}\"");
                continue;
            }

            $totalBobot += $bobot;

            $totalPctRencana = 0;
            $weeklyData = [];

            for ($minggu = 1; $minggu <= 100; $minggu++) {
                $rencanaKey = 'w' . $minggu . '_rencana';
                $keteranganKey = 'w' . $minggu . '_keterangan';
                
                // Convert row to array for consistent access
                $rowData = $row->toArray();
                
                if (!array_key_exists($rencanaKey, $rowData)) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d: Kolom "W%d - Rencana (%%)" tidak ditemukan untuk work group "%s"',
                            $rowNumber,
                            $minggu,
                            $workGroupName
                        )
                    );
                    continue;
                }

                $pctRencana = $rowData[$rencanaKey];

                // Handle empty cells - treat as 0.00
                if ($pctRencana === null || $pctRencana === '' || $pctRencana === '-') {
                    $pctRencana = 0.00;
                }

                if (!is_numeric($pctRencana)) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, W%d: Rencana harus berupa angka untuk work group "%s"',
                            $rowNumber,
                            $minggu,
                            $workGroupName
                        )
                    );
                    continue;
                }

                $pctRencana = (float) $pctRencana;

                if ($pctRencana < 0 || $pctRencana > 100) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, W%d: Rencana harus antara 0-100 untuk work group "%s"',
                            $rowNumber,
                            $minggu,
                            $workGroupName
                        )
                    );
                    continue;
                }

                $totalPctRencana += $pctRencana;

                $keterangan = array_key_exists($keteranganKey, $rowData) ? trim($rowData[$keteranganKey]) : null;
                if (!empty($keterangan) && strlen($keterangan) > 500) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, W%d: Keterangan maksimal 500 karakter untuk work group "%s"',
                            $rowNumber,
                            $minggu,
                            $workGroupName
                        )
                    );
                    $keterangan = substr($keterangan, 0, 500);
                }

                $weeklyData[$minggu] = [
                    'minggu_ke' => $minggu,
                    'pct_rencana' => $pctRencana,
                    'keterangan' => $keterangan,
                ];
            }

            if (count($weeklyData) !== 100) {
                $this->parent->addError(
                    sprintf(
                        'Baris %d: Work group "%s" harus memiliki 100 minggu. Ditemukan: %d minggu',
                        $rowNumber,
                        $workGroupName,
                        count($weeklyData)
                    )
                );
            }

            if (abs($totalPctRencana - 100.00) > 0.05) {
                $this->parent->addError(
                    sprintf(
                        'Baris %d: Work group "%s" total Kumulatif Rencana harus = 100.00%%. Total saat ini: %.2f%%',
                        $rowNumber,
                        $workGroupName,
                        $totalPctRencana
                    )
                );
            }

            $workGroupsData[$workGroupName] = [
                'bobot' => $bobot,
                'sort_order' => $no ?? ($index + 1),
                'weekly_data' => $weeklyData,
            ];
        }

        if (abs($totalBobot - 100.00) > 0.01) {
            $this->parent->addError(
                sprintf(
                    'Total BOBOT semua work groups harus = 100.00%%. Total saat ini: %.2f%%',
                    $totalBobot
                )
            );
        }

        // Store data in parent class to fix instance issue
        $this->parent->setRencanaData($workGroupsData);
    }

    public function getRencanaData(): array
    {
        return $this->rencanaData;
    }
}
