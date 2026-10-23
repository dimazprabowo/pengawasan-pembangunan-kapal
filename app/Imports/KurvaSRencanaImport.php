<?php

namespace App\Imports;

use App\Models\JenisKapal;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class KurvaSRencanaImport implements ToCollection, WithHeadingRow
{
    protected JenisKapal $jenisKapal;

    protected KurvaSTemplateImport $parent;

    public function __construct(JenisKapal $jenisKapal, KurvaSTemplateImport $parent)
    {
        $this->jenisKapal = $jenisKapal;
        $this->parent = $parent;
    }

    public function collection(Collection $rows)
    {
        $rows = $rows->filter(function ($row) {
            return ! empty($row['work_group']);
        });

        if ($rows->isEmpty()) {
            $this->parent->addError('Sheet Rencana: Tidak ada data yang valid.');

            return;
        }

        // Deteksi jumlah bulan dari header Excel
        $maxMonths = $this->detectMaxMonthsFromHeaders($rows->first());

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

            if (! is_numeric($bobot)) {
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
            $monthlyData = [];

            for ($bulan = 1; $bulan <= $maxMonths; $bulan++) {
                $rencanaKey = 'b'.$bulan.'_rencana';
                $keteranganKey = 'b'.$bulan.'_keterangan';

                // Convert row to array for consistent access
                $rowData = $row->toArray();

                // Skip jika kolom tidak ada (untuk fleksibilitas jumlah bulan)
                if (! array_key_exists($rencanaKey, $rowData)) {
                    break; // Berhenti di bulan ini, tidak ada kolom lagi
                }

                $pctRencana = $rowData[$rencanaKey];

                // Handle empty cells - treat as 0.00
                if ($pctRencana === null || $pctRencana === '' || $pctRencana === '-') {
                    $pctRencana = 0.00;
                }

                if (! is_numeric($pctRencana)) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, B%d: Rencana harus berupa angka untuk work group "%s"',
                            $rowNumber,
                            $bulan,
                            $workGroupName
                        )
                    );

                    continue;
                }

                $pctRencana = (float) $pctRencana;

                // Rencana dalam poin % proyek — satu bulan tidak boleh melebihi bobot group
                if ($pctRencana < 0 || $pctRencana > $bobot) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, B%d: Rencana harus antara 0-%.2f (bobot) untuk work group "%s"',
                            $rowNumber,
                            $bulan,
                            $bobot,
                            $workGroupName
                        )
                    );

                    continue;
                }

                $totalPctRencana += $pctRencana;

                $keterangan = array_key_exists($keteranganKey, $rowData) ? trim($rowData[$keteranganKey]) : null;
                if (! empty($keterangan) && strlen($keterangan) > 500) {
                    $this->parent->addError(
                        sprintf(
                            'Baris %d, B%d: Keterangan maksimal 500 karakter untuk work group "%s"',
                            $rowNumber,
                            $bulan,
                            $workGroupName
                        )
                    );
                    $keterangan = substr($keterangan, 0, 500);
                }

                $monthlyData[$bulan] = [
                    'bulan_ke' => $bulan,
                    'pct_rencana' => $pctRencana,
                    'keterangan' => $keterangan,
                ];
            }

            // Validasi minimal ada data bulan
            if (empty($monthlyData)) {
                $this->parent->addError(
                    sprintf(
                        'Baris %d: Work group "%s" tidak memiliki data bulan',
                        $rowNumber,
                        $workGroupName
                    )
                );
            }

            if ($totalPctRencana > $bobot + 0.05) {
                $this->parent->addError(
                    sprintf(
                        'Baris %d: Work group "%s" total Kumulatif Rencana melebihi bobotnya (%.2f%%). Total saat ini: %.2f%%',
                        $rowNumber,
                        $workGroupName,
                        $bobot,
                        $totalPctRencana
                    )
                );
            }

            $workGroupsData[$workGroupName] = [
                'bobot' => $bobot,
                'sort_order' => $no ?? ($index + 1),
                'monthly_data' => $monthlyData,
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

    /**
     * Detect maximum months from Excel headers
     */
    protected function detectMaxMonthsFromHeaders($firstRow): int
    {
        if (! $firstRow) {
            return 24; // Default fallback
        }

        $rowData = $firstRow->toArray();
        $maxMonth = 0;

        // Cari kolom dengan pattern b{number}_rencana
        foreach (array_keys($rowData) as $key) {
            if (preg_match('/^b(\d+)_rencana$/', $key, $matches)) {
                $monthNumber = (int) $matches[1];
                if ($monthNumber > $maxMonth) {
                    $maxMonth = $monthNumber;
                }
            }
        }

        // Jika tidak ditemukan, fallback ke 24
        return $maxMonth > 0 ? $maxMonth : 24;
    }
}
