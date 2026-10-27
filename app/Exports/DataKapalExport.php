<?php

namespace App\Exports;

use App\Models\DataKapal;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DataKapalExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $search;

    protected $statusFilter;

    protected $companyFilter;

    public function __construct($search = '', $statusFilter = '', $companyFilter = null)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
        $this->companyFilter = $companyFilter;
    }

    public function collection(): Collection
    {
        return DataKapal::with('company')
            ->withCount(['laporanHarian', 'laporanMingguan', 'laporanBulanan'])
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                        ->orWhere('deskripsi', 'like', "%{$this->search}%")
                        ->orWhereHas('company', function ($q) {
                            $q->where('name', 'like', "%{$this->search}%")
                                ->orWhere('code', 'like', "%{$this->search}%");
                        });
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->when($this->companyFilter, function ($q) {
                $q->where('company_id', $this->companyFilter);
            })
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Data Kapal',
            'Perusahaan',
            'Kode Perusahaan',
            'Deskripsi',
            'Laporan Harian',
            'Laporan Mingguan',
            'Laporan Bulanan',
            'Status',
            'Dibuat Pada',
        ];
    }

    public function map($dataKapal): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $dataKapal->nama,
            $dataKapal->company->name,
            $dataKapal->company->code,
            $dataKapal->deskripsi ?? '-',
            $dataKapal->getLaporanCount('harian'),
            $dataKapal->getLaporanCount('mingguan'),
            $dataKapal->getLaporanCount('bulanan'),
            $dataKapal->status->label(),
            $dataKapal->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): ?array
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

    public function title(): string
    {
        return 'Data Kapal';
    }
}
