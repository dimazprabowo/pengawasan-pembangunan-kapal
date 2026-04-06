<?php

namespace App\Services;

use App\Models\Laporan;
use PhpOffice\PhpWord\TemplateProcessor;

class LaporanWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-harian/Template Laporan Harian.docx';
    private const MAX_IMAGES    = 10;

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // PUBLIC ENTRY POINT
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    /**
     * Generate Word document from template for a Laporan Harian.
     * Returns storage-relative path (e.g. "laporan/word/laporan-harian-1-20260406.docx").
     */
    public function generate(Laporan $laporan): string
    {
        $templateFullPath = storage_path('app/' . self::TEMPLATE_PATH);

        if (!file_exists($templateFullPath)) {
            throw new \RuntimeException(
                'Template Word tidak ditemukan di: storage/app/' . self::TEMPLATE_PATH
            );
        }

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

        $processor = new TemplateProcessor($templateFullPath);

        $this->fillBasicInfo($processor, $laporan);
        $this->fillWeather($processor, $laporan);
        $this->fillPersonel($processor, $laporan);
        $this->fillPeralatan($processor, $laporan);
        $this->fillConsumable($processor, $laporan);
        $this->fillAktivitas($processor, $laporan);
        $this->fillLampiran($processor, $laporan);
        $this->fillSignature($processor, $laporan);

        return $this->saveDocument($processor, $laporan);
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // FILL METHODS
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function fillBasicInfo(TemplateProcessor $p, Laporan $laporan): void
    {
        $p->setValue('pekerjaan',   $this->safe($laporan->judul));
        $p->setValue('lokasi',      $this->safe($laporan->jenisKapal?->galangan?->nama));
        $p->setValue('no_kapal',    $this->safe($laporan->jenisKapal?->nama));
        $p->setValue('perusahaan',  $this->safe($laporan->jenisKapal?->company?->name));
        $p->setValue('tanggal',     $laporan->tanggal_laporan->translatedFormat('d F Y'));
        $p->setValue('dibuat_oleh', $this->safe($laporan->user?->name));
        $p->setValue('no_laporan',  (string) $laporan->id);
    }

    private function fillWeather(TemplateProcessor $p, Laporan $laporan): void
    {
        $suhu = $laporan->suhu ? number_format((float) $laporan->suhu, 1) . 'Â°C' : '-';
        $p->setValue('suhu',             $suhu);
        $p->setValue('cuaca_pagi',       $this->safe($laporan->cuacaPagi?->nama));
        $p->setValue('kelembaban_pagi',  $this->safe($laporan->kelembabanPagi?->nama));
        $p->setValue('cuaca_siang',      $this->safe($laporan->cuacaSiang?->nama));
        $p->setValue('kelembaban_siang', $this->safe($laporan->kelembabanSiang?->nama));
        $p->setValue('cuaca_sore',       $this->safe($laporan->cuacaSore?->nama));
        $p->setValue('kelembaban_sore',  $this->safe($laporan->kelembabanSore?->nama));
    }

    private function fillPersonel(TemplateProcessor $p, Laporan $laporan): void
    {
        $rows = $laporan->personel;
        $count = max(1, $rows->count());
        $p->cloneRow('personel_no', $count);

        if ($rows->isEmpty()) {
            $p->setValue('personel_no#1',         '-');
            $p->setValue('personel_jabatan#1',    '-');
            $p->setValue('personel_status#1',     '-');
            $p->setValue('personel_keterangan#1', '-');
            return;
        }

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->setValue("personel_no#{$n}",         (string) $n);
            $p->setValue("personel_jabatan#{$n}",    $this->safe($row->jabatan));
            $p->setValue("personel_status#{$n}",     $this->safe($row->status));
            $p->setValue("personel_keterangan#{$n}", $this->safe($row->keterangan));
        }
    }

    private function fillPeralatan(TemplateProcessor $p, Laporan $laporan): void
    {
        $rows = $laporan->peralatan;
        $count = max(1, $rows->count());
        $p->cloneRow('peralatan_no', $count);

        if ($rows->isEmpty()) {
            $p->setValue('peralatan_no#1',         '-');
            $p->setValue('peralatan_jenis#1',      '-');
            $p->setValue('peralatan_jumlah#1',     '-');
            $p->setValue('peralatan_keterangan#1', '-');
            return;
        }

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->setValue("peralatan_no#{$n}",         (string) $n);
            $p->setValue("peralatan_jenis#{$n}",      $this->safe($row->jenis));
            $p->setValue("peralatan_jumlah#{$n}",     (string) ($row->jumlah ?? '-'));
            $p->setValue("peralatan_keterangan#{$n}", $this->safe($row->keterangan));
        }
    }

    private function fillConsumable(TemplateProcessor $p, Laporan $laporan): void
    {
        $rows = $laporan->consumable;
        $count = max(1, $rows->count());
        $p->cloneRow('consumable_no', $count);

        if ($rows->isEmpty()) {
            $p->setValue('consumable_no#1',         '-');
            $p->setValue('consumable_jenis#1',      '-');
            $p->setValue('consumable_jumlah#1',     '-');
            $p->setValue('consumable_keterangan#1', '-');
            return;
        }

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->setValue("consumable_no#{$n}",         (string) $n);
            $p->setValue("consumable_jenis#{$n}",      $this->safe($row->jenis));
            $p->setValue("consumable_jumlah#{$n}",     (string) ($row->jumlah ?? '-'));
            $p->setValue("consumable_keterangan#{$n}", $this->safe($row->keterangan));
        }
    }

    private function fillAktivitas(TemplateProcessor $p, Laporan $laporan): void
    {
        $rows = $laporan->aktivitas;
        $count = max(1, $rows->count());
        $p->cloneRow('aktivitas_no', $count);

        if ($rows->isEmpty()) {
            $p->setValue('aktivitas_no#1',        '-');
            $p->setValue('aktivitas_kategori#1',  '-');
            $p->setValue('aktivitas_aktivitas#1', '-');
            $p->setValue('aktivitas_pic#1',       '-');
            return;
        }

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->setValue("aktivitas_no#{$n}",        (string) $n);
            $p->setValue("aktivitas_kategori#{$n}",  $this->safe($row->kategori));
            $p->setValue("aktivitas_aktivitas#{$n}", $this->safe($row->aktivitas));
            $p->setValue("aktivitas_pic#{$n}",       $this->safe($row->pic));
        }
    }

    private function fillLampiran(TemplateProcessor $p, Laporan $laporan): void
    {
        $images = $laporan->lampiran
            ->filter(fn($l) => $l->isFileCompleted() && $l->isImage())
            ->values();

        for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
            $lamp = $images->get($i - 1);

            $fullPath = $lamp ? storage_path('app/' . $lamp->file_path) : null;
            if ($lamp && $fullPath && file_exists($fullPath)) {
                try {
                    $p->setImageValue("lampiran_{$i}", [
                        'path'   => $fullPath,
                        'width'  => 400,
                        'height' => 300,
                        'ratio'  => true,
                    ]);
                } catch (\Exception $e) {
                    $p->setValue("lampiran_{$i}", '[Gambar tidak dapat dimuat]');
                }
                $p->setValue("lampiran_ket_{$i}", $this->safe($lamp->keterangan));
            } else {
                $p->setValue("lampiran_{$i}",     '');
                $p->setValue("lampiran_ket_{$i}", '');
            }
        }
    }

    private function fillSignature(TemplateProcessor $p, Laporan $laporan): void
    {
        $p->setValue('ttd_kota',    $this->safe($laporan->jenisKapal?->galangan?->kota));
        $p->setValue('ttd_tanggal', $laporan->tanggal_laporan->translatedFormat('d F Y'));
        $p->setValue('ttd_nama',    $this->safe($laporan->user?->name));
    }

    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
    // HELPERS
    // â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

    private function saveDocument(TemplateProcessor $processor, Laporan $laporan): string
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

    private function safe(?string $value): string
    {
        return $value ?? '-';
    }
}
