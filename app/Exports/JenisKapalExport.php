<?php

namespace App\Exports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class JenisKapalExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
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

    public function collection()
    {
        return JenisKapal::with('company')
            ->withCount('laporanHarian')
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
            'Jenis Kapal',
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

    public function map($jenisKapal): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $jenisKapal->nama,
            $jenisKapal->company->name,
            $jenisKapal->company->code,
            $jenisKapal->deskripsi ?? '-',
            $jenisKapal->getLaporanCount('harian'),
            $jenisKapal->getLaporanCount('mingguan'),
            $jenisKapal->getLaporanCount('bulanan'),
            $jenisKapal->status->label(),
            $jenisKapal->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
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

    public function title(): string
    {
        return 'Jenis Kapal';
    }
}
