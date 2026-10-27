<?php

namespace App\Exports;

use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Services\KontrakPeriodeService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KurvaSRencanaSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    protected DataKapal $dataKapal;

    protected bool $withData;

    protected int $months;

    public function __construct(DataKapal $dataKapal, bool $withData = true, ?int $months = null)
    {
        $this->dataKapal = $dataKapal;
        $this->withData = $withData;
        $this->months = $months ?? static::resolveMonths($dataKapal);
    }

    /**
     * Jumlah kolom bulan pada file: ikut total bulan kontrak bila ada,
     * fallback ke bulan terbesar di data, default 24.
     */
    public static function resolveMonths(DataKapal $dataKapal): int
    {
        $kontrak = $dataKapal->hasKontrak()
            ? app(KontrakPeriodeService::class)->getTotalBulan($dataKapal)
            : 0;

        $dbMax = (int) (KurvaSRencana::whereHas(
            'workGroup',
            fn ($q) => $q->where('data_kapal_id', $dataKapal->id)
        )->max('bulan_ke') ?? 0);

        $months = max($kontrak, $dbMax);

        return $months > 0 ? $months : 24;
    }

    public function array(): array
    {
        // Jika user minta template kosong, langsung return default template
        if (! $this->withData) {
            return $this->getDefaultTemplate();
        }

        // Jika minta dengan data, ambil dari database
        $workGroups = $this->dataKapal->kurvaSWorkGroups()
            ->with('kurvaSRencana')
            ->orderBy('sort_order')
            ->get();

        // Jika tidak ada data di database, return default template
        if ($workGroups->isEmpty()) {
            return $this->getDefaultTemplate();
        }

        // Export dengan data yang ada
        $data = [];
        foreach ($workGroups as $index => $wg) {
            $rencanaData = $wg->kurvaSRencana->keyBy('bulan_ke');

            $row = [
                $index + 1,
                $wg->nama,
                (float) $wg->bobot,
            ];

            for ($bulan = 1; $bulan <= $this->months; $bulan++) {
                $rencana = $rencanaData->get($bulan);
                $row[] = $rencana ? (float) $rencana->pct_rencana : 0.00;
                $row[] = $rencana?->keterangan ?? '';
            }
            $data[] = $row;
        }

        return $data;
    }

    protected function getDefaultTemplate(): array
    {
        $defaultWorkGroups = [
            ['nama' => 'Engineering & Design', 'bobot' => 10.00],
            ['nama' => 'Konstruksi Lambung', 'bobot' => 30.00],
            ['nama' => 'Permesinan & Perpipaan', 'bobot' => 20.00],
            ['nama' => 'Kelistrikan & Instrumentasi', 'bobot' => 15.00],
            ['nama' => 'Perlengkapan Kapal (Outfitting)', 'bobot' => 15.00],
            ['nama' => 'Pengecatan & Surface Treatment', 'bobot' => 5.00],
            ['nama' => 'Uji Coba & Komisioning', 'bobot' => 5.00],
        ];

        $data = [];
        foreach ($defaultWorkGroups as $index => $wg) {
            $row = [
                $index + 1,
                $wg['nama'],
                $wg['bobot'],
            ];

            for ($bulan = 1; $bulan <= $this->months; $bulan++) {
                $row[] = 0.00;
                $row[] = '';
            }
            $data[] = $row;
        }

        return $data;
    }

    public function headings(): array
    {
        $headings = [
            'No',
            'Work Group',
            'Bobot (%)',
            'Kumulatif Rencana (%)',
            'Sisa Bobot (%)',
        ];

        // Jumlah kolom bulan mengikuti kontrak (fallback data/24)
        $maxMonths = $this->months;

        for ($bulan = 1; $bulan <= $maxMonths; $bulan++) {
            $headings[] = "B{$bulan} - Rencana (%)";
            $headings[] = "B{$bulan} - Keterangan";
        }

        return $headings;
    }

    public function title(): string
    {
        return 'Rencana';
    }

    public function columnWidths(): array
    {
        $widths = [
            'A' => 5,
            'B' => 35,
            'C' => 10,
            'D' => 18,
            'E' => 18,
        ];

        // Jumlah kolom bulan mengikuti kontrak (fallback data/24)
        $maxMonths = $this->months;

        $colIndex = 6;
        for ($bulan = 1; $bulan <= $maxMonths; $bulan++) {
            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $widths[$colLetter] = 10;
            $colIndex++;

            $colLetter = Coordinate::stringFromColumnIndex($colIndex);
            $widths[$colLetter] = 25;
            $colIndex++;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): ?array
    {
        // Jumlah kolom bulan mengikuti kontrak (fallback data/24)
        $maxMonths = $this->months;
        $lastColIndex = 5 + ($maxMonths * 2);
        $lastCol = Coordinate::stringFromColumnIndex($lastColIndex);
        $dataRows = count($this->array());
        $lastDataRow = $dataRows + 1;

        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 9,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1F2937'],
                ],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);

        $sheet->getStyle("A2:{$lastCol}{$lastDataRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB'],
                ],
            ],
            'font' => ['size' => 9],
        ]);

        $sheet->getStyle("A2:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C2:E{$lastDataRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle("C2:E{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $bgColor = ($row % 2 === 0) ? 'F3F4F6' : 'FFFFFF';
            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bgColor],
                ],
            ]);

            $rencanaColStart = 6;
            $rencanaFormulaParts = [];
            for ($bulan = 1; $bulan <= $maxMonths; $bulan++) {
                $rencanaCol = Coordinate::stringFromColumnIndex($rencanaColStart);
                $rencanaFormulaParts[] = "{$rencanaCol}{$row}";

                $sheet->getStyle("{$rencanaCol}{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                $sheet->getStyle("{$rencanaCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                $rencanaColStart += 2;
            }

            $kumulatifRencanaFormula = '=SUM('.implode(',', $rencanaFormulaParts).')';
            $sheet->setCellValue("D{$row}", $kumulatifRencanaFormula);

            // Sisa bobot = Bobot - Kumulatif Rencana (0 berarti rencana sudah penuh)
            $sisaBobotFormula = "=C{$row}-D{$row}";
            $sheet->setCellValue("E{$row}", $sisaBobotFormula);

            $sheet->getStyle("E{$row}")->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D1FAE5'],
                ],
            ]);
        }

        $totalRow = $lastDataRow + 2;
        $sheet->setCellValue("A{$totalRow}", '');
        $sheet->setCellValue("B{$totalRow}", 'TOTAL');
        $sheet->setCellValue("C{$totalRow}", "=SUM(C2:C{$lastDataRow})");
        $sheet->setCellValue("D{$totalRow}", '');
        $sheet->setCellValue("E{$totalRow}", '');

        $sheet->getStyle("A{$totalRow}:E{$totalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '059669'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => '1F2937'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $noteRow = $totalRow + 2;
        $sheet->setCellValue("A{$noteRow}", "⚠ PENTING:\n• Total BOBOT semua work group harus = 100.00%\n• Rencana (%) diisi dalam satuan % PROYEK per BULAN — Kumulatif Rencana per work group tidak boleh melebihi Bobot-nya (target = Bobot)\n• Sisa Bobot = Bobot − Kumulatif Rencana (AUTO-CALCULATED; 0.00 berarti rencana group sudah penuh)\n• Isi Rencana (%) dan Keterangan untuk setiap bulan");
        $sheet->mergeCells("A{$noteRow}:E{$noteRow}");
        $sheet->getStyle("A{$noteRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626'], 'size' => 10],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEE2E2'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_TOP,
                'wrapText' => true,
            ],
        ]);
        $sheet->getRowDimension($noteRow)->setRowHeight(60);

        $sheet->setAutoFilter("A1:{$lastCol}1");
        $sheet->freezePane('F2');

        return [];
    }
}
