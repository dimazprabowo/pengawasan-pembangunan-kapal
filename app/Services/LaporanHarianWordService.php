<?php

namespace App\Services;

use App\Models\LaporanHarian;
use PhpOffice\PhpWord\TemplateProcessor;

class LaporanHarianWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-harian/template-laporan-harian.docx';
    private const MAX_ROWS      = 30;
    private const MAX_LAMPIRAN  = 10;

    // ────────────────────────────────────────────────────────────────────────────
    // PUBLIC ENTRY POINT
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Generate Word document dari template dengan TemplateProcessor.
     * Returns storage-relative path (e.g. "laporan/word/laporan-harian-1-20260406.docx").
     */
    public function generate(LaporanHarian $laporan): string
    {
        $laporan->loadMissing([
            'user',
            'jenisKapal.company',
            'jenisKapal.galangan',
            'cuacaPagi',
            'kelembabanPagi',
            'cuacaSiang',
            'kelembabanSiang',
            'cuacaSore',
            'kelembabanSore',
            'personel',
            'peralatan',
            'consumable',
            'aktivitas',
            'lampiran',
        ]);

        // Try to use template from Jenis Kapal first
        $templateFullPath = null;
        $templateSource = 'default';

        if ($laporan->jenisKapal && $laporan->jenisKapal->hasTemplate('harian')) {
            $templateFullPath = $laporan->jenisKapal->getTemplateFullPath('harian');
            $templateSource = 'jenis_kapal';
        }
        
        // Fall back to default template if Jenis Kapal template not available
        if (!$templateFullPath || !file_exists($templateFullPath)) {
            $templateFullPath = storage_path('app/' . self::TEMPLATE_PATH);
            $templateSource = 'default';
            
            if (!file_exists($templateFullPath)) {
                throw new \RuntimeException(
                    'Template Word tidak ditemukan. Harap upload template pada master Jenis Kapal atau pastikan template default tersedia di: storage/app/' . self::TEMPLATE_PATH
                );
            }
        }

        $processor = new TemplateProcessor($templateFullPath);

        // Set single values (basic info, weather, signature)
        $this->setSingleValues($processor, $laporan);

        // Fill table rows (personel, peralatan, consumable, aktivitas, lampiran)
        $this->fillTableRows($processor, $laporan);

        // Save document
        return $this->saveDocument($processor, $laporan);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // SET SINGLE VALUES
    // ────────────────────────────────────────────────────────────────────────────

    private function setSingleValues(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $values = [];

        // Basic Info
        $values['judul'] = $laporan->judul ?? '-';
        $values['lokasi'] = $laporan->jenisKapal?->galangan?->nama ?? '-';
        $values['no_kapal'] = $laporan->jenisKapal?->nama ?? '-';
        $values['perusahaan'] = $laporan->jenisKapal?->company?->name ?? '-';
        $values['tanggal'] = $laporan->tanggal_laporan ? $laporan->tanggal_laporan->translatedFormat('d F Y') : '-';
        $values['dibuat_oleh'] = $laporan->user?->name ?? '-';
        $values['no_laporan'] = (string) $laporan->id;

        // Weather - use '-' for empty values
        $values['suhu'] = $laporan->suhu ? number_format((float) $laporan->suhu, 1) . '°C' : '-';
        $values['cuaca_pagi'] = $laporan->cuacaPagi?->nama ?? '-';
        $values['kelembaban_pagi'] = $laporan->kelembabanPagi?->nama ?? '-';
        $values['cuaca_siang'] = $laporan->cuacaSiang?->nama ?? '-';
        $values['kelembaban_siang'] = $laporan->kelembabanSiang?->nama ?? '-';
        $values['cuaca_sore'] = $laporan->cuacaSore?->nama ?? '-';
        $values['kelembaban_sore'] = $laporan->kelembabanSore?->nama ?? '-';

        // Totals
        $values['personel_total'] = (string) $laporan->personel->count();
        $values['peralatan_total'] = (string) $laporan->peralatan->count();
        $values['consumable_total'] = (string) $laporan->consumable->count();
        $values['aktivitas_total'] = (string) $laporan->aktivitas->count();
        $values['lampiran_total'] = (string) $laporan->lampiran->filter(fn($l) => $l->isFileCompleted())->count();

        // Signature
        $values['ttd_kota'] = $laporan->jenisKapal?->galangan?->kota ?? '-';
        $values['ttd_tanggal'] = $laporan->tanggal_laporan ? $laporan->tanggal_laporan->translatedFormat('d F Y') : '-';
        $values['ttd_nama'] = $laporan->user?->name ?? '-';

        $processor->setValues($values);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // FILL TABLE ROWS
    // ────────────────────────────────────────────────────────────────────────────

    private function fillTableRows(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $this->fillPersonelTable($processor, $laporan);
        $this->fillPeralatanTable($processor, $laporan);
        $this->fillConsumableTable($processor, $laporan);
        $this->fillAktivitasTable($processor, $laporan);
        $this->fillLampiranTable($processor, $laporan);
    }

    private function fillPersonelTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $rows = $laporan->personel->values();
        
        if ($rows->isEmpty()) {
            // Jika tidak ada data, isi dengan satu row berisi '-'
            $processor->setValue('personel_no', '1');
            $processor->setValue('personel_jabatan', '-');
            $processor->setValue('personel_status', '-');
            $processor->setValue('personel_keterangan', '-');
            
            return;
        }

        // Clone row sebanyak jumlah data
        $processor->cloneRow('personel_no', $rows->count());

        // Set values untuk setiap row yang sudah di-clone
        foreach ($rows as $index => $row) {
            $i = $index + 1;
            
            // Format: placeholder#index (misal: personel_no#1, personel_no#2)
            $processor->setValue('personel_no#' . $i, (string) $i);
            $processor->setValue('personel_jabatan#' . $i, $row->jabatan ?? '-');
            $processor->setValue('personel_status#' . $i, $row->status ?? '-');
            $processor->setValue('personel_keterangan#' . $i, $row->keterangan ?? '-');
        }
    }

    private function fillPeralatanTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $rows = $laporan->peralatan->values();
        
        if ($rows->isEmpty()) {
            // Jika tidak ada data, isi dengan satu row berisi '-'
            $processor->setValue('peralatan_no', '1');
            $processor->setValue('peralatan_jenis', '-');
            $processor->setValue('peralatan_jumlah', '-');
            $processor->setValue('peralatan_keterangan', '-');
            return;
        }

        try {
            $processor->cloneRow('peralatan_no', $rows->count());

            foreach ($rows as $index => $row) {
                $i = $index + 1;
                
                $processor->setValue('peralatan_no#' . $i, (string) $i);
                $processor->setValue('peralatan_jenis#' . $i, $row->jenis ?? '-');
                $processor->setValue('peralatan_jumlah#' . $i, $row->jumlah !== null ? (string) $row->jumlah : '-');
                $processor->setValue('peralatan_keterangan#' . $i, $row->keterangan ?? '-');
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping peralatan table (placeholder not found or contains markup)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fillConsumableTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $rows = $laporan->consumable->values();
        
        if ($rows->isEmpty()) {
            // Jika tidak ada data, isi dengan satu row berisi '-'
            $processor->setValue('consumable_no', '1');
            $processor->setValue('consumable_jenis', '-');
            $processor->setValue('consumable_jumlah', '-');
            $processor->setValue('consumable_keterangan', '-');
            return;
        }

        try {
            $processor->cloneRow('consumable_no', $rows->count());

            foreach ($rows as $index => $row) {
                $i = $index + 1;
                
                $processor->setValue('consumable_no#' . $i, (string) $i);
                $processor->setValue('consumable_jenis#' . $i, $row->jenis ?? '-');
                $processor->setValue('consumable_jumlah#' . $i, $row->jumlah !== null ? (string) $row->jumlah : '-');
                $processor->setValue('consumable_keterangan#' . $i, $row->keterangan ?? '-');
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping consumable table (placeholder not found or contains markup)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fillAktivitasTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $rows = $laporan->aktivitas->values();
        
        if ($rows->isEmpty()) {
            // Jika tidak ada data, isi dengan satu row berisi '-'
            $processor->setValue('aktivitas_no', '1');
            $processor->setValue('aktivitas_kategori', '-');
            $processor->setValue('aktivitas_aktivitas', '-');
            $processor->setValue('aktivitas_pic', '-');
            return;
        }

        try {
            $processor->cloneRow('aktivitas_no', $rows->count());

            foreach ($rows as $index => $row) {
                $i = $index + 1;
                
                $processor->setValue('aktivitas_no#' . $i, (string) $i);
                $processor->setValue('aktivitas_kategori#' . $i, $row->kategori ?? '-');
                $processor->setValue('aktivitas_aktivitas#' . $i, $row->aktivitas ?? '-');
                $processor->setValue('aktivitas_pic#' . $i, $row->pic ?? '-');
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping aktivitas table (placeholder not found or contains markup)', [
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fillLampiranTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $rows = $laporan->lampiran->filter(fn($l) => $l->isFileCompleted())->values();
        
        if ($rows->isEmpty()) {
            // Jika tidak ada data, isi dengan satu row berisi '-'
            $processor->setValue('lampiran_no', '1');
            $processor->setValue('lampiran_gambar', '-');
            $processor->setValue('lampiran_ket', '-');
            return;
        }

        // Clone row sebanyak jumlah data
        $processor->cloneRow('lampiran_no', $rows->count());

        // Set values untuk setiap row yang sudah di-clone
        foreach ($rows as $index => $item) {
            $i = $index + 1;
            
            // Set nomor
            $processor->setValue('lampiran_no#' . $i, (string) $i);
            
            // Set gambar jika file adalah image
            if ($this->isImageFile($item->file_path)) {
                // Try with 'private/' prefix first (Laravel's default for file uploads)
                $imagePath = storage_path('app/private/' . $item->file_path);
                
                // If not found, try without 'private/' prefix
                if (!file_exists($imagePath)) {
                    $imagePath = storage_path('app/' . $item->file_path);
                }
                
                if (file_exists($imagePath)) {
                    try {
                        // Convert WebP to PNG if needed (PhpWord doesn't support WebP)
                        $finalImagePath = $this->prepareImageForWord($imagePath);
                        
                        $processor->setImageValue('lampiran_gambar#' . $i, [
                            'path' => $finalImagePath,
                            'width' => 200,
                            'height' => 150,
                            'ratio' => true
                        ]);
                        
                        // Clean up temporary PNG file if it was created
                        if ($finalImagePath !== $imagePath && file_exists($finalImagePath)) {
                            @unlink($finalImagePath);
                        }
                        
                    } catch (\Exception $e) {
                        // Jika gagal insert image, set nama file saja
                        \Log::error('LaporanWordService: Failed to insert image', [
                            'lampiran_id' => $item->id,
                            'file_path' => $item->file_path,
                            'full_path' => $imagePath,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
                    }
                } else {
                    \Log::warning('LaporanWordService: Image file not found', [
                        'lampiran_id' => $item->id,
                        'file_path' => $item->file_path,
                        'full_path' => $imagePath,
                        'storage_app_exists' => is_dir(storage_path('app'))
                    ]);
                    $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
                }
            } else {
                // Jika bukan image, tampilkan nama file
                $processor->setValue('lampiran_gambar#' . $i, $item->file_name ?? '-');
            }
            
            // Set keterangan
            $processor->setValue('lampiran_ket#' . $i, $item->keterangan ?? '-');
        }
    }

    private function isImageFile(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

        return in_array($extension, $imageExtensions);
    }

    /**
     * Prepare image for Word document embedding.
     * PhpWord doesn't support WebP, so we convert it to PNG temporarily.
     * 
     * @param string $imagePath Full path to the image file
     * @return string Path to the image ready for Word (original or converted)
     */
    private function prepareImageForWord(string $imagePath): string
    {
        $extension = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
        
        // If not WebP, return original path
        if ($extension !== 'webp') {
            return $imagePath;
        }

        // Convert WebP to PNG using Intervention Image
        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image = $manager->read($imagePath);
            
            // Create temporary PNG file
            $tempPngPath = sys_get_temp_dir() . '/' . uniqid('word_img_') . '.png';
            $image->toPng()->save($tempPngPath);
            
            return $tempPngPath;
        } catch (\Exception $e) {
            \Log::error('LaporanWordService: Failed to convert WebP to PNG', [
                'image_path' => $imagePath,
                'error' => $e->getMessage()
            ]);
            
            // Return original path as fallback (will likely fail, but logged)
            return $imagePath;
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────────────────────

    private function saveDocument(TemplateProcessor $processor, LaporanHarian $laporan): string
    {
        $dir = storage_path('app/laporan/word');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf(
            'laporan-harian-%d-%s.docx',
            $laporan->id,
            now()->format('YmdHis')
        );

        $processor->saveAs($dir . '/' . $filename);

        return 'laporan/word/' . $filename;
    }
}
