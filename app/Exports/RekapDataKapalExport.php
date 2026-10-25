<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapDataKapalExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(protected Collection $items) {}

    public function collection()
    {
        return $this->items;
    }

    public function headings(): array
    {
        return [
            'No',
            'Data Kapal',
            'Perusahaan',
            'Galangan',
            'Laporan Harian',
            'Laporan Mingguan',
            'Progress (%)',
            'Laporan Terakhir',
        ];
    }

    public function map($dataKapal): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $dataKapal->nama,
            $dataKapal->company->name ?? '-',
            $dataKapal->galangan->nama ?? '-',
            $dataKapal->laporan_harian_count,
            $dataKapal->laporan_mingguan_count,
            $dataKapal->progress_aktual !== null ? $dataKapal->progress_aktual.'%' : '-',
            $dataKapal->laporan_harian_max_tanggal_laporan
                ? \Carbon\Carbon::parse($dataKapal->laporan_harian_max_tanggal_laporan)->format('d/m/Y')
                : 'Belum ada',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }
}
