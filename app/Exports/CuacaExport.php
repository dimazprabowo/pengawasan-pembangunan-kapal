<?php

namespace App\Exports;

use App\Models\Cuaca;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CuacaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        return Cuaca::query()
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('nama', 'like', "%{$this->search}%")
                      ->orWhere('keterangan', 'like', "%{$this->search}%");
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
            'Nama Cuaca',
            'Keterangan',
            'Status',
            'Dibuat Pada',
        ];
    }

    public function map($cuaca): array
    {
        return [
            $cuaca->nama,
            $cuaca->keterangan ?? '-',
            $cuaca->status->label(),
            $cuaca->created_at->format('d/m/Y H:i'),
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
        return 'Data Cuaca';
    }
}
