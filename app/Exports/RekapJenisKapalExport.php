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

class RekapJenisKapalExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'Jenis Kapal',
            'Perusahaan',
            'Galangan',
            'Laporan Harian',
            'Laporan Mingguan',
            'Progress (%)',
            'Laporan Terakhir',
        ];
    }

    public function map($jenisKapal): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $jenisKapal->nama,
            $jenisKapal->company->name ?? '-',
            $jenisKapal->galangan->nama ?? '-',
            $jenisKapal->laporan_harian_count,
            $jenisKapal->laporan_mingguan_count,
            $jenisKapal->progress_aktual !== null ? $jenisKapal->progress_aktual.'%' : '-',
            $jenisKapal->laporan_harian_max_tanggal_laporan
                ? \Carbon\Carbon::parse($jenisKapal->laporan_harian_max_tanggal_laporan)->format('d/m/Y')
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
