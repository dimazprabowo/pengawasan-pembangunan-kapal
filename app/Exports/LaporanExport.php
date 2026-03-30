<?php

namespace App\Exports;

use App\Models\Laporan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    use Exportable;

    protected string $tipe;
    protected ?string $search;

    public function __construct(string $tipe, ?string $search = null)
    {
        $this->tipe = $tipe;
        $this->search = $search;
    }

    public function query()
    {
        $query = Laporan::with('user')->where('tipe', $this->tipe);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('judul', 'like', "%{$this->search}%")
                  ->orWhere('isi', 'like', "%{$this->search}%")
                  ->orWhereHas('user', function ($q) {
                      $q->where('name', 'like', "%{$this->search}%");
                  });
            });
        }

        return $query->orderByDesc('tanggal_laporan')->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'No',
            'Judul',
            'Tanggal Laporan',
            'Tipe',
            'Pembuat',
            'Isi Laporan',
            'Catatan',
            'File Lampiran',
            'Tanggal Dibuat',
        ];
    }

    public function map($laporan): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $laporan->judul,
            $laporan->tanggal_laporan->format('d/m/Y'),
            $laporan->tipe->label(),
            $laporan->user->name ?? '-',
            $laporan->isi ?? '-',
            $laporan->catatan ?? '-',
            $laporan->file_name ?? '-',
            $laporan->created_at->format('d/m/Y H:i'),
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
