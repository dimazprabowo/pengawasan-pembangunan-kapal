<?php

namespace App\Exports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class KurvaSInstructionsSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    protected JenisKapal $jenisKapal;

    public function __construct(JenisKapal $jenisKapal)
    {
        $this->jenisKapal = $jenisKapal;
    }

    public function array(): array
    {
        return [
            ['TEMPLATE KURVA S - PERENCANAAN PEMBANGUNAN KAPAL'],
            [''],
            ['📋 INFORMASI JENIS KAPAL'],
            ['Nama Jenis Kapal', $this->jenisKapal->nama],
            ['Perusahaan', $this->jenisKapal->company?->name ?? '-'],
            ['Galangan', $this->jenisKapal->galangan?->nama ?? '-'],
            ['Tanggal Export', now()->format('d/m/Y H:i:s')],
            [''],
            ['📖 CARA PENGGUNAAN'],
            [''],
            ['STRUKTUR TEMPLATE'],
            ['Template ini terdiri dari 2 sheet:'],
            ['  1. Petunjuk (sheet ini) - Panduan penggunaan'],
            ['  2. Rencana - Data work groups dan rencana mingguan (W1-W100)'],
            [''],
            ['KOLOM UTAMA (Sheet Rencana)'],
            ['  • No - Nomor urut work group (1, 2, 3, ...)'],
            ['  • Work Group - Nama kelompok pekerjaan (Engineering, Konstruksi, dll)'],
            ['  • Bobot (%) - Bobot work group terhadap total proyek'],
            ['  • Kumulatif Rencana (%) - Total rencana W1-W100 (AUTO-CALCULATED)'],
            ['  • Kumulatif Aktual (%) - Kontribusi terhadap proyek (AUTO-CALCULATED)'],
            ['  • W1-W100 Rencana (%) - Persentase rencana per minggu'],
            ['  • W1-W100 Keterangan - Catatan untuk setiap minggu (opsional)'],
            [''],
            ['CARA MENGISI'],
            ['  1. Isi kolom Work Group dengan nama kelompok pekerjaan'],
            ['  2. Isi kolom Bobot (%) - Total semua work group HARUS = 100%'],
            ['  3. Isi W1-W100 Rencana (%) - Total per work group HARUS = 100%'],
            ['  4. Tambahkan Keterangan jika diperlukan (max 500 karakter)'],
            ['  5. Kumulatif Rencana dan Kumulatif Aktual otomatis terhitung'],
            [''],
            ['FORMULA AUTO-CALCULATED'],
            ['  • Kumulatif Rencana = SUM(W1 + W2 + ... + W100)'],
            ['  • Kumulatif Aktual = (Bobot × Kumulatif Rencana) / 100'],
            [''],
            ['VALIDASI WAJIB'],
            ['  ✓ Total Bobot semua work groups = 100.00%'],
            ['  ✓ Total Rencana per work group (W1-W100) = 100.00%'],
            ['  ✓ Bobot: 0.01 - 100.00'],
            ['  ✓ Rencana: 0.00 - 100.00'],
            ['  ✓ Minimal 1 work group, maksimal 20 work groups'],
            [''],
            ['CARA IMPORT'],
            ['  1. Isi data pada sheet "Rencana"'],
            ['  2. Simpan file (format .xlsx)'],
            ['  3. Buka aplikasi > Menu Jenis Kapal'],
            ['  4. Klik tombol "Kurva S" > "Import Kurva S"'],
            ['  5. Upload file dan tunggu validasi'],
            ['  6. Data lama akan DIHAPUS dan diganti dengan data baru'],
            [''],
            ['⚠️ PERINGATAN PENTING'],
            ['  • JANGAN ubah nama sheet atau struktur kolom'],
            ['  • JANGAN hapus formula di kolom Kumulatif Rencana & Aktual'],
            ['  • Gunakan titik (.) untuk desimal, bukan koma (,)'],
            ['  • Backup data lama sebelum import'],
            ['  • Pastikan total validasi terpenuhi sebelum import'],
        ];
    }

    public function title(): string
    {
        return 'Petunjuk';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 35,
            'B' => 50,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Title
        $sheet->getStyle('A1:B1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E40AF'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A1:B1');
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Info Section Header
        $sheet->getStyle('A3:B3')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
        ]);
        $sheet->mergeCells('A3:B3');
        $sheet->getStyle('A4:A7')->getFont()->setBold(true);

        // Main Header
        $sheet->getStyle('A9:B9')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '059669'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);
        $sheet->mergeCells('A9:B9');
        $sheet->getRowDimension(9)->setRowHeight(25);

        // Section Headers (Gray)
        $sectionHeaders = [11, 16, 25, 32, 37, 43, 51];
        foreach ($sectionHeaders as $row) {
            $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '1F2937']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ]);
            $sheet->mergeCells("A{$row}:B{$row}");
        }

        // Warning Section
        $sheet->getStyle('A57:B57')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DC2626'],
            ],
        ]);
        $sheet->mergeCells('A57:B57');

        // Merge all instruction content rows (A:B) except info rows (4-7)
        $mergeRows = [
            12, 13, 14, 17, 18, 19, 20, 21, 22, 23,
            26, 27, 28, 29, 30, 33, 34, 38, 39, 40, 41, 42,
            44, 45, 46, 47, 48, 52, 53, 54, 55, 56, 58, 59, 60, 61, 62
        ];
        
        foreach ($mergeRows as $row) {
            $sheet->mergeCells("A{$row}:B{$row}");
        }

        // Wrap text for all cells
        $sheet->getStyle('A1:B62')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:B62')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [];
    }
}
