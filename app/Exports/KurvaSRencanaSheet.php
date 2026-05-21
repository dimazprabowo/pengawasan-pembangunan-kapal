<?php

namespace App\Exports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class KurvaSRencanaSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    protected JenisKapal $jenisKapal;
    protected bool $withData;

    public function __construct(JenisKapal $jenisKapal, bool $withData = true)
    {
        $this->jenisKapal = $jenisKapal;
        $this->withData = $withData;
    }

    public function array(): array
    {
        $workGroups = $this->jenisKapal->kurvaSWorkGroups()
            ->with('kurvaSRencana')
            ->orderBy('sort_order')
            ->get();

        if (!$this->withData || $workGroups->isEmpty()) {
            return $this->getDefaultTemplate();
        }

        $data = [];
        foreach ($workGroups as $index => $wg) {
            $rencanaData = $wg->kurvaSRencana->keyBy('minggu_ke');
            
            $row = [
                $index + 1,
                $wg->nama,
                (float) $wg->bobot,
            ];
            
            for ($minggu = 1; $minggu <= 100; $minggu++) {
                $rencana = $rencanaData->get($minggu);
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
            
            for ($minggu = 1; $minggu <= 100; $minggu++) {
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
            'Kumulatif Aktual (%)',
        ];
        
        for ($minggu = 1; $minggu <= 100; $minggu++) {
            $headings[] = "W{$minggu} - Rencana (%)";
            $headings[] = "W{$minggu} - Keterangan";
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
        
        $colIndex = 6;
        for ($minggu = 1; $minggu <= 100; $minggu++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $widths[$colLetter] = 10;
            $colIndex++;
            
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $widths[$colLetter] = 25;
            $colIndex++;
        }
        
        return $widths;
    }

    public function styles(Worksheet $sheet)
    {
        $lastColIndex = 5 + (100 * 2);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
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
            for ($minggu = 1; $minggu <= 100; $minggu++) {
                $rencanaCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($rencanaColStart);
                $rencanaFormulaParts[] = "{$rencanaCol}{$row}";
                
                $sheet->getStyle("{$rencanaCol}{$row}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
                $sheet->getStyle("{$rencanaCol}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $rencanaColStart += 2;
            }
            
            $kumulatifRencanaFormula = '=SUM(' . implode(',', $rencanaFormulaParts) . ')';
            $sheet->setCellValue("D{$row}", $kumulatifRencanaFormula);
            
            $kumulatifAktualFormula = "=(C{$row}*D{$row})/100";
            $sheet->setCellValue("E{$row}", $kumulatifAktualFormula);
            
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
        $sheet->setCellValue("A{$noteRow}", "⚠ PENTING:\n• Total BOBOT harus = 100.00%\n• Kumulatif Rencana per work group harus = 100.00%\n• Kumulatif Aktual = (Bobot × Kumulatif Rencana) / 100 (AUTO-CALCULATED)\n• Isi Rencana (%) dan Keterangan untuk setiap minggu (W1-W100)");
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
