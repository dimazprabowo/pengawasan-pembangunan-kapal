<?php

namespace App\Exports;

use App\Models\LaporanBulanan;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanBulananExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    protected ?string $search;

    protected ?int $dataKapalId;

    public function __construct(?string $search = null, ?int $dataKapalId = null)
    {
        $this->search = $search;
        $this->dataKapalId = $dataKapalId;
    }

    public function query(): Builder
    {
        $query = LaporanBulanan::with(['user', 'dataKapal.company', 'dataKapal.galangan']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('ringkasan', 'like', "%{$this->search}%")
                    ->orWhereHas('user', function ($q) {
                        $q->where('name', 'like', "%{$this->search}%");
                    })
                    ->orWhereHas('dataKapal', function ($q) {
                        $q->where('nama', 'like', "%{$this->search}%");
                    });

                if (is_numeric($this->search)) {
                    $q->orWhere('bulan_ke', (int) $this->search);
                }
            });
        }

        if ($this->dataKapalId) {
            $query->where('data_kapal_id', $this->dataKapalId);
        }

        return $query->orderByDesc('tanggal_laporan')->orderByDesc('created_at');
    }

    public function headings(): array
    {
        return [
            'No',
            'Judul',
            'Periode',
            'Tanggal Laporan',
            'Data Kapal',
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
            $this->formatPeriode($laporan),
            $laporan->tanggal_laporan->format('d/m/Y'),
            $laporan->dataKapal->nama ?? '-',
            $laporan->dataKapal->company->name ?? '-',
            $laporan->dataKapal->galangan->nama ?? '-',
            $laporan->user->name ?? '-',
            $laporan->created_at->format('d/m/Y H:i'),
        ];
    }

    private function formatPeriode($laporan): string
    {
        if ($laporan->periode_mulai && $laporan->periode_selesai) {
            return $laporan->periode_mulai->format('d/m/Y').' - '.$laporan->periode_selesai->format('d/m/Y');
        }

        return $laporan->bulan_ke ? 'Bulan ke-'.$laporan->bulan_ke : '-';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2563EB'],
                ],
            ],
        ];
    }
}
