<?php

namespace App\Exports;

use App\Models\Galangan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GalanganExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected ?string $search;
    protected ?string $statusFilter;

    public function __construct(?string $search = null, ?string $statusFilter = null)
    {
        $this->search = $search;
        $this->statusFilter = $statusFilter;
    }

    public function collection()
    {
        return Galangan::query()
            ->withCount('jenisKapal')
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('kode', 'like', "%{$this->search}%")
                      ->orWhere('kota', 'like', "%{$this->search}%")
                      ->orWhere('provinsi', 'like', "%{$this->search}%")
                      ->orWhere('pic_name', 'like', "%{$this->search}%");
                });
            })
            ->when($this->statusFilter !== null && $this->statusFilter !== '', function ($q) {
                $q->where('status', $this->statusFilter);
            })
            ->orderBy('nama')
            ->get();
    }

    public function headings(): array
    {
        return [
            'Kode',
            'Nama Galangan',
            'Kota',
            'Provinsi',
            'Telepon',
            'Email',
            'PIC',
            'Telepon PIC',
            'Jumlah Jenis Kapal',
            'Status',
            'Dibuat Pada',
        ];
    }

    public function map($galangan): array
    {
        return [
            $galangan->kode,
            $galangan->nama,
            $galangan->kota ?? '-',
            $galangan->provinsi ?? '-',
            $galangan->telepon ?? '-',
            $galangan->email ?? '-',
            $galangan->pic_name ?? '-',
            $galangan->pic_phone ?? '-',
            $galangan->jenis_kapal_count,
            $galangan->status->label(),
            $galangan->created_at->format('d/m/Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Data Galangan';
    }
}
