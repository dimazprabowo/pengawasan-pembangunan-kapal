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

class KurvaSWorkGroupsSheet implements FromArray, WithTitle, WithHeadings, WithStyles, WithColumnWidths
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
        if (!$this->withData) {
            return [
                [1, 'Engineering & Design', 10.00],
                [2, 'Konstruksi Lambung', 30.00],
                [3, 'Permesinan & Perpipaan', 20.00],
                [4, 'Kelistrikan & Instrumentasi', 15.00],
                [5, 'Perlengkapan Kapal (Outfitting)', 15.00],
                [6, 'Pengecatan & Surface Treatment', 5.00],
                [7, 'Uji Coba & Komisioning', 5.00],
            ];
        }

        $workGroups = $this->jenisKapal->kurvaSWorkGroups()
            ->orderBy('sort_order')
            ->get();

        if ($workGroups->isEmpty()) {
            return [
                [1, 'Engineering & Design', 10.00],
                [2, 'Konstruksi Lambung', 30.00],
                [3, 'Permesinan & Perpipaan', 20.00],
                [4, 'Kelistrikan & Instrumentasi', 15.00],
                [5, 'Perlengkapan Kapal (Outfitting)', 15.00],
                [6, 'Pengecatan & Surface Treatment', 5.00],
                [7, 'Uji Coba & Komisioning', 5.00],
            ];
        }

        $data = [];
        foreach ($workGroups as $index => $wg) {
            $data[] = [
                $index + 1,
                $wg->nama,
                (float) $wg->bobot,
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama Work Group',
            'Bobot (%)',
        ];
    }

    public function title(): string
    {
        return 'Work Groups';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 45,
            'C' => 15,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:C1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '1F2937'],
                ],
            ],
        ]);

        $lastRow = count($this->array()) + 1;
        
        $sheet->getStyle("A2:C{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB'],
                ],
            ],
        ]);

        $sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C2:C' . $lastRow)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle('C2:C' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $totalRow = $lastRow + 1;
        $sheet->setCellValue("B{$totalRow}", 'TOTAL');
        $sheet->setCellValue("C{$totalRow}", "=SUM(C2:C{$lastRow})");
        
        $sheet->getStyle("B{$totalRow}:C{$totalRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
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
        ]);
        $sheet->getStyle("C{$totalRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_00);
        $sheet->getStyle("C{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $noteRow = $totalRow + 2;
        $sheet->setCellValue("A{$noteRow}", '⚠ PENTING: Total Bobot HARUS = 100.00%');
        $sheet->mergeCells("A{$noteRow}:C{$noteRow}");
        $sheet->getStyle("A{$noteRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEE2E2'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        return [];
    }
}
