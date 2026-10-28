<?php

namespace App\Exports;

use App\Services\LaporanNcService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanNcExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(
        protected int $dataKapalId,
        protected ?string $search = null,
        protected ?string $statusFilter = null,
        protected array $mingguFilter = [],
        protected array $bulanFilter = [],
        protected array $hariFilter = [],
    ) {}

    public function query(): Builder
    {
        return app(LaporanNcService::class)->filteredQuery(
            $this->dataKapalId,
            $this->search,
            $this->statusFilter,
            $this->mingguFilter,
            $this->bulanFilter,
            $this->hariFilter,
        );
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor NC',
            'Tanggal Laporan',
            'Kendala',
            'Tindakan',
            'PIC',
            'Status',
            'Closed',
            'Dibuat Oleh',
            'Tanggal Dibuat',
        ];
    }

    public function map($nc): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $nc->nomor ?? '-',
            $nc->laporanHarian?->tanggal_laporan?->format('d/m/Y') ?? '-',
            $nc->kendala ?? '-',
            $nc->tindakan ?? '-',
            $nc->pic ?? '-',
            $nc->status->label(),
            $nc->closed_at?->format('d/m/Y') ?? '-',
            $nc->createdBy?->name ?? '-',
            $nc->created_at?->format('d/m/Y H:i') ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            ],
        ];
    }
}
