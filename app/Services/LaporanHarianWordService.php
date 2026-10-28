<?php

namespace App\Services;

use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

class LaporanHarianWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-harian/template-laporan-harian.docx';

    // Grid lampiran 2x2 → maksimal 4 foto per halaman
    private const FOTO_PER_HALAMAN = 4;

    // Ukuran kotak foto di sel grid lampiran (rasio asli dipertahankan)
    private const FOTO_SIZE = '7cm';

    public function __construct(
        private WordImageService $imageService,
        private LaporanHarianService $laporanHarianService,
    ) {}

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
            'dataKapal.company',
            'dataKapal.galangan',
            'cuacaPagi',
            'kelembabanPagi',
            'cuacaSiang',
            'kelembabanSiang',
            'cuacaSore',
            'kelembabanSore',
            'personel',
            'peralatan',
            'consumable',
            'aktivitas.workGroup',
            'aktivitas.lampiran',
            'lampiran',
        ]);

        // Try to use template from Data Kapal first
        $templateFullPath = null;
        $templateSource = 'default';

        if ($laporan->dataKapal && $laporan->dataKapal->hasTemplate('harian')) {
            $templateFullPath = $laporan->dataKapal->getTemplateFullPath('harian');
            $templateSource = 'data_kapal';
        }

        // Fall back to default template if Data Kapal template not available
        if (! $templateFullPath || ! file_exists($templateFullPath)) {
            $templateFullPath = storage_path('app/'.self::TEMPLATE_PATH);
            $templateSource = 'default';

            if (! file_exists($templateFullPath)) {
                throw new \RuntimeException(
                    'Template Word tidak ditemukan. Harap upload template pada master Data Kapal atau pastikan template default tersedia di: storage/app/'.self::TEMPLATE_PATH
                );
            }
        }

        // Escape nilai teks (mis. "Engineering & Design") agar XML dokumen tetap valid
        Settings::setOutputEscapingEnabled(true);
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
        $values['lokasi'] = $laporan->dataKapal?->galangan?->nama ?? '-';
        $values['no_kapal'] = $laporan->dataKapal?->nama ?? '-';
        $values['perusahaan'] = $laporan->dataKapal?->company?->name ?? '-';
        $values['tanggal'] = $laporan->tanggal_laporan ? $laporan->tanggal_laporan->translatedFormat('d F Y') : '-';
        $values['dibuat_oleh'] = $laporan->user?->name ?? '-';
        $values['no_laporan'] = (string) $laporan->id;

        // Weather - use '-' for empty values
        $values['suhu'] = $laporan->suhu ? number_format((float) $laporan->suhu, 1).'°C' : '-';
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
        $values['lampiran_total'] = (string) $laporan->lampiran->filter(fn ($l) => $l->isFileCompleted())->count();

        // Signature
        $values['ttd_kota'] = $laporan->dataKapal?->galangan?->kota ?? '-';
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
        // NC sengaja tidak diekspor di dokumen harian — rekap NC hanya di laporan
        // mingguan. Blok di template dibersihkan (marker ${blok_nc} tidak ikut render).
        try {
            $processor->cloneBlock('blok_nc', 0, true, true);
            $processor->cloneBlock('blok_nc_foto', 0, true, true);
        } catch (\Exception) {
            // Template tanpa blok NC — tidak ada yang perlu dihapus
        }
        $this->fillLampiranSection($processor, $laporan);
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
            $processor->setValue('personel_no#'.$i, (string) $i);
            $processor->setValue('personel_jabatan#'.$i, $row->jabatan ?? '-');
            $processor->setValue('personel_status#'.$i, $row->status ?? '-');
            $processor->setValue('personel_keterangan#'.$i, $row->keterangan ?? '-');
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

                $processor->setValue('peralatan_no#'.$i, (string) $i);
                $processor->setValue('peralatan_jenis#'.$i, $row->jenis ?? '-');
                $processor->setValue('peralatan_jumlah#'.$i, $row->jumlah !== null ? (string) $row->jumlah : '-');
                $processor->setValue('peralatan_keterangan#'.$i, $row->keterangan ?? '-');
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping peralatan table (placeholder not found or contains markup)', [
                'error' => $e->getMessage(),
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

                $processor->setValue('consumable_no#'.$i, (string) $i);
                $processor->setValue('consumable_jenis#'.$i, $row->jenis ?? '-');
                $processor->setValue('consumable_jumlah#'.$i, $row->jumlah !== null ? (string) $row->jumlah : '-');
                $processor->setValue('consumable_keterangan#'.$i, $row->keterangan ?? '-');
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping consumable table (placeholder not found or contains markup)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Uraian Aktivitas per kategori: blok ${blok_kategori} (judul kategori + tabel)
     * di-clone per kategori yang memiliki aktivitas; kategori kosong tidak digenerate.
     */
    private function fillAktivitasTable(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $groups = $this->laporanHarianService->aktivitasPerKategori($laporan);

        try {
            if ($groups->isEmpty()) {
                // Tidak ada aktivitas sama sekali → satu blok berisi '-'
                $processor->cloneBlock('blok_kategori', 1, true, true);
                $processor->setValue('aktivitas_kategori#1', '-');
                $processor->setValue('aktivitas_no#1', '1');
                $processor->setValue('aktivitas_aktivitas#1', '-');
                $processor->setValue('aktivitas_pic#1', '-');

                return;
            }

            $processor->cloneBlock('blok_kategori', $groups->count(), true, true);

            foreach ($groups as $g => $group) {
                $b = $g + 1;
                $processor->setValue("aktivitas_kategori#{$b}", $group['nama']);
                $processor->cloneRow("aktivitas_no#{$b}", $group['aktivitas']->count());

                foreach ($group['aktivitas'] as $r => $row) {
                    $i = $r + 1;
                    $processor->setValue("aktivitas_no#{$b}#{$i}", (string) $i);
                    $processor->setValue("aktivitas_aktivitas#{$b}#{$i}", $row->aktivitas ?? '-');
                    $processor->setValue("aktivitas_pic#{$b}#{$i}", $row->pic ?? '-');
                }
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping aktivitas section (template tanpa blok ${blok_kategori}?)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lampiran dokumentasi: grid 2x2 per halaman (blok ${blok_lampiran}). Tiap slot berisi
     * kategori (atas), foto, dan uraian aktivitas (bawah). Urutan mengikuti kategori & aktivitas.
     */
    private function fillLampiranSection(TemplateProcessor $processor, LaporanHarian $laporan): void
    {
        $fotos = $this->laporanHarianService->aktivitasPerKategori($laporan)
            ->flatMap(fn ($group) => $group['aktivitas']->map(fn ($a) => ['kategori' => $group['nama'], 'aktivitas' => $a]))
            ->filter(fn ($slot) => $slot['aktivitas']->lampiran?->isFileCompleted()
                && $this->imageService->isImageFile($slot['aktivitas']->lampiran->file_path))
            ->values();
        $pages = $fotos->chunk(self::FOTO_PER_HALAMAN)->values();

        try {
            $processor->setValue('lampiran_kosong', $fotos->isEmpty() ? 'Tidak ada dokumentasi foto.' : '');
            // 0 halaman → blok dihapus
            $processor->cloneBlock('blok_lampiran', $pages->count(), true, true);

            foreach ($pages as $p => $page) {
                $b = $p + 1;
                $page = $page->values();

                // Page break hanya di antara halaman (halaman pertama sudah diawali judul Lampiran)
                $processor->replaceXmlBlock("lampiran_pemisah#{$b}", $b === 1 ? '' : '<w:p><w:r><w:br w:type="page"/></w:r></w:p>');

                // Halaman dengan ≤ 2 foto: baris pasangan bawah (slot 3-4) dihapus
                $slotCount = $page->count() <= 2 ? 2 : 4;
                if ($slotCount === 2) {
                    foreach (['lp_kategori_3', 'lp_gambar_3', 'lp_uraian_3'] as $macro) {
                        $processor->deleteRow("{$macro}#{$b}");
                    }
                }

                for ($slot = 1; $slot <= $slotCount; $slot++) {
                    $item = $page->get($slot - 1);
                    $processor->setValue("lp_kategori_{$slot}#{$b}", $item['kategori'] ?? '');
                    $processor->setValue("lp_uraian_{$slot}#{$b}", $item ? ($item['aktivitas']->aktivitas ?? '-') : '');

                    if ($item) {
                        $this->setFotoImage($processor, "lp_gambar_{$slot}#{$b}", $item['aktivitas']->lampiran);
                    } else {
                        $processor->setValue("lp_gambar_{$slot}#{$b}", '');
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::warning('LaporanWordService: Skipping lampiran section (template tanpa blok ${blok_lampiran}?)', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function setFotoImage(TemplateProcessor $processor, string $macro, LaporanLampiran $lampiran): void
    {
        $imagePath = $this->imageService->resolveLampiranPath($lampiran->file_path);

        if (! $imagePath || ! file_exists($imagePath)) {
            \Log::warning('LaporanWordService: Image file not found', [
                'lampiran_id' => $lampiran->id,
                'file_path' => $lampiran->file_path,
            ]);
            $processor->setValue($macro, $lampiran->file_name ?? '-');

            return;
        }

        try {
            // Resolusi lebih tinggi dari default karena foto tampil besar di grid 2x2
            $finalImagePath = $this->imageService->prepareImageForWord($imagePath, 900, 900, 75);

            $processor->setImageValue($macro, [
                'path' => $finalImagePath,
                'width' => self::FOTO_SIZE,
                'height' => self::FOTO_SIZE,
                'ratio' => true,
            ]);

            if ($finalImagePath !== $imagePath && file_exists($finalImagePath)) {
                @unlink($finalImagePath);
            }
        } catch (\Exception $e) {
            \Log::error('LaporanWordService: Failed to insert image', [
                'lampiran_id' => $lampiran->id,
                'error' => $e->getMessage(),
            ]);
            $processor->setValue($macro, $lampiran->file_name ?? '-');
        }
    }

    // ────────────────────────────────────────────────────────────────────────────
    // SAVE DOCUMENT
    // ────────────────────────────────────────────────────────────────────────────

    private function saveDocument(TemplateProcessor $processor, LaporanHarian $laporan): string
    {
        $dir = storage_path('app/laporan/word');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf(
            'laporan-harian-%d-%s.docx',
            $laporan->id,
            now()->format('YmdHis')
        );

        $processor->saveAs($dir.'/'.$filename);

        return 'laporan/word/'.$filename;
    }
}
