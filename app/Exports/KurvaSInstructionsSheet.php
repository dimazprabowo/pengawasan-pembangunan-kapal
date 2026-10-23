<?php

namespace App\Exports;

use App\Models\JenisKapal;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KurvaSInstructionsSheet implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    protected JenisKapal $jenisKapal;

    protected ?int $months;

    public function __construct(JenisKapal $jenisKapal, ?int $months = null)
    {
        $this->jenisKapal = $jenisKapal;
        $this->months = $months;
    }

    protected function monthsLabel(): string
    {
        return $this->months ? "B1-B{$this->months}" : 'B1-B…';
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
            ['  2. Rencana - Data work groups dan rencana bulanan ('.$this->monthsLabel().', mengikuti durasi kontrak)'],
            [''],
            ['KOLOM UTAMA (Sheet Rencana)'],
            ['  • No - Nomor urut work group (1, 2, 3, ...)'],
            ['  • Work Group - Nama kelompok pekerjaan (Engineering, Konstruksi, dll)'],
            ['  • Bobot (%) - Porsi maksimal work group terhadap total proyek'],
            ['  • Kumulatif Rencana (%) - Total rencana semua bulan (AUTO-CALCULATED)'],
            ['  • Sisa Bobot (%) - Bobot dikurangi Kumulatif Rencana (AUTO-CALCULATED)'],
            ['  • '.$this->monthsLabel().' Rencana (%) - Kontribusi rencana per bulan dalam % proyek'],
            ['  • '.$this->monthsLabel().' Keterangan - Catatan untuk setiap bulan (opsional)'],
            [''],
            ['CARA MENGISI'],
            ['  1. Isi kolom Work Group dengan nama kelompok pekerjaan'],
            ['  2. Isi kolom Bobot (%) - Total semua work group HARUS = 100%'],
            ['  3. Isi '.$this->monthsLabel().' Rencana (%) dalam satuan % PROYEK per BULAN - Total per work group TIDAK BOLEH melebihi Bobot-nya (target = Bobot)'],
            ['  4. Rencana bulanan dibagi rata ke minggu-minggu di bulan tsb oleh sistem; realisasi tetap diisi per minggu di laporan mingguan'],
            ['  5. Tambahkan Keterangan jika diperlukan (max 500 karakter)'],
            ['  6. Kumulatif Rencana dan Sisa Bobot otomatis terhitung'],
            [''],
            ['FORMULA AUTO-CALCULATED'],
            ['  • Kumulatif Rencana = SUM(B1 + B2 + ... + B'.($this->months ?? '…').')'],
            ['  • Sisa Bobot = Bobot - Kumulatif Rencana'],
            [''],
            ['VALIDASI WAJIB'],
            ['  ✓ Total Bobot semua work groups = 100.00%'],
            ['  ✓ Total Rencana per work group tidak boleh melebihi Bobot-nya'],
            ['  ✓ Bobot: 0.01 - 100.00'],
            ['  ✓ Rencana: 0.00 - maksimal Bobot work group'],
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
            ['  • JANGAN hapus formula di kolom Kumulatif Rencana & Sisa Bobot'],
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
        $sectionHeaders = [11, 16, 25, 33, 37, 44, 52];
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
        $sheet->getStyle('A52:B52')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DC2626'],
            ],
        ]);
        $sheet->mergeCells('A52:B52');

        // Merge all instruction content rows (A:B) except info rows (4-7)
        $mergeRows = [
            12, 13, 14, 17, 18, 19, 20, 21, 22, 23,
            26, 27, 28, 29, 30, 31, 34, 35, 38, 39, 40, 41, 42,
            45, 46, 47, 48, 49, 50, 53, 54, 55, 56, 57,
        ];

        foreach ($mergeRows as $row) {
            $sheet->mergeCells("A{$row}:B{$row}");
        }

        // Wrap text for all cells
        $sheet->getStyle('A1:B57')->getAlignment()->setWrapText(true);
        $sheet->getStyle('A1:B57')->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

        return [];
    }
}
