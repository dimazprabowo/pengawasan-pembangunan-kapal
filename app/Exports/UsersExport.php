<?php

namespace App\Exports;

use App\Models\User;
use App\Traits\HasDynamicLike;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    use Exportable, HasDynamicLike;

    protected ?string $search;
    protected ?string $roleFilter;
    protected ?string $statusFilter;

    public function __construct(?string $search = null, ?string $roleFilter = null, ?string $statusFilter = null)
    {
        $this->search = $search;
        $this->roleFilter = $roleFilter;
        $this->statusFilter = $statusFilter;
    }

    public function query()
    {
        $query = User::with(['roles', 'company']);

        if ($this->search) {
            $operator = $this->getLikeOperator();
            $query->where(function ($q) use ($operator) {
                $q->where('name', $operator, "%{$this->search}%")
                  ->orWhere('email', $operator, "%{$this->search}%")
                  ->orWhere('phone', $operator, "%{$this->search}%")
                  ->orWhere('position', $operator, "%{$this->search}%");
            });
        }

        if ($this->roleFilter) {
            $query->role($this->roleFilter);
        }

        if ($this->statusFilter !== null && $this->statusFilter !== '') {
            $query->where('is_active', $this->statusFilter === '1');
        }

        return $query->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'No',
            'Nama',
            'Email',
            'Telepon',
            'Posisi/Jabatan',
            'Role',
            'Perusahaan',
            'Status',
            'Tanggal Dibuat',
        ];
    }

    public function map($user): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $user->name,
            $user->email,
            $user->phone ?? '-',
            $user->position ?? '-',
            ucfirst($user->getRoleNames()->join(', ') ?: 'No Role'),
            $user->company->name ?? '-',
            $user->is_active ? 'Aktif' : 'Nonaktif',
            $user->created_at->format('d/m/Y H:i'),
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
