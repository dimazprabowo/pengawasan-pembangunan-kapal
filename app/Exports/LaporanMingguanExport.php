<?php

namespace App\Exports;

use App\Models\LaporanMingguan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanMingguanExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    use Exportable;

    protected ?string $search;
    protected ?int $jenisKapalId;

    public function __construct(?string $search = null, ?int $jenisKapalId = null)
    {
        $this->search = $search;
        $this->jenisKapalId = $jenisKapalId;
    }

    public function query()
    {
        $query = LaporanMingguan::with(['user', 'jenisKapal.company', 'jenisKapal.galangan']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('judul', 'like', "%{$this->search}%")
                  ->orWhereHas('user', function ($q) {
                      $q->where('name', 'like', "%{$this->search}%");
                  })
                  ->orWhereHas('jenisKapal', function ($q) {
                      $q->where('nama', 'like', "%{$this->search}%");
                  });
            });
        }

        if ($this->jenisKapalId) {
            $query->where('jenis_kapal_id', $this->jenisKapalId);
        }

        return $query->orderByDesc('tanggal_laporan')->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'No',
            'Judul',
            'Tanggal Laporan',
            'Jenis Kapal',
            'Perusahaan',
            'Galangan',
            'Pembuat',
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
            $laporan->jenisKapal->nama ?? '-',
            $laporan->jenisKapal->company->name ?? '-',
            $laporan->jenisKapal->galangan->nama ?? '-',
            $laporan->user->name ?? '-',
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
