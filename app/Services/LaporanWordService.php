<?php

namespace App\Services;

use App\Models\Laporan;
use ZipArchive;

class LaporanWordService
{
    private const TEMPLATE_PATH = 'templates/laporan-harian/Template Laporan Harian.docx';
    private const MAX_ROWS      = 30;
    private const MAX_LAMPIRAN  = 10;

    // ────────────────────────────────────────────────────────────────────────────
    // PUBLIC ENTRY POINT
    // ────────────────────────────────────────────────────────────────────────────

    /**
     * Copy template asli dan replace placeholder di XML.
     * Preserve semua format, gambar, header, footer, dll.
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

        // Prepare output path
        $outputPath = $this->prepareOutputPath($laporan);

        // Copy template to output
        if (!copy($templateFullPath, $outputPath)) {
            throw new \RuntimeException('Gagal copy template Word');
        }

        // Build replacements array
        $replacements = $this->buildReplacements($laporan);

        // Replace placeholders in document XML
        $this->replaceInDocument($outputPath, $replacements);

        return $this->getRelativePath($outputPath);
    }

    // ────────────────────────────────────────────────────────────────────────────
    // BUILD REPLACEMENTS
    // ────────────────────────────────────────────────────────────────────────────

    private function buildReplacements(Laporan $laporan): array
    {
        $replacements = [];

        // Basic Info
        if ($laporan->judul) {
            $replacements['judul'] = $laporan->judul;
        }
        if ($laporan->jenisKapal?->galangan?->nama) {
            $replacements['lokasi'] = $laporan->jenisKapal->galangan->nama;
        }
        if ($laporan->jenisKapal?->nama) {
            $replacements['no_kapal'] = $laporan->jenisKapal->nama;
        }
        if ($laporan->jenisKapal?->company?->name) {
            $replacements['perusahaan'] = $laporan->jenisKapal->company->name;
        }
        if ($laporan->tanggal_laporan) {
            $replacements['tanggal'] = $laporan->tanggal_laporan->translatedFormat('d F Y');
        }
        if ($laporan->user?->name) {
            $replacements['dibuat_oleh'] = $laporan->user->name;
        }
        $replacements['no_laporan'] = (string) $laporan->id;

        // Weather
        if ($laporan->suhu) {
            $replacements['suhu'] = number_format((float) $laporan->suhu, 1) . '°C';
        }
        if ($laporan->cuacaPagi?->nama) {
            $replacements['cuaca_pagi'] = $laporan->cuacaPagi->nama;
        }
        if ($laporan->kelembabanPagi?->nama) {
            $replacements['kelembaban_pagi'] = $laporan->kelembabanPagi->nama;
        }
        if ($laporan->cuacaSiang?->nama) {
            $replacements['cuaca_siang'] = $laporan->cuacaSiang->nama;
        }
        if ($laporan->kelembabanSiang?->nama) {
            $replacements['kelembaban_siang'] = $laporan->kelembabanSiang->nama;
        }
        if ($laporan->cuacaSore?->nama) {
            $replacements['cuaca_sore'] = $laporan->cuacaSore->nama;
        }
        if ($laporan->kelembabanSore?->nama) {
            $replacements['kelembaban_sore'] = $laporan->kelembabanSore->nama;
        }

        // Personel
        $personel = $laporan->personel->values();
        foreach ($personel as $index => $row) {
            $i = $index + 1;
            if ($i > self::MAX_ROWS) break;

            $replacements["personel_no_{$i}"] = (string) $i;
            if ($row->jabatan) {
                $replacements["personel_jabatan_{$i}"] = $row->jabatan;
            }
            if ($row->status) {
                $replacements["personel_status_{$i}"] = $row->status;
            }
            if ($row->keterangan) {
                $replacements["personel_keterangan_{$i}"] = $row->keterangan;
            }
        }
        if ($personel->count() > 0) {
            $replacements['personel_total'] = (string) $personel->count();
        }

        // Peralatan
        $peralatan = $laporan->peralatan->values();
        foreach ($peralatan as $index => $row) {
            $i = $index + 1;
            if ($i > self::MAX_ROWS) break;

            $replacements["peralatan_no_{$i}"] = (string) $i;
            if ($row->jenis) {
                $replacements["peralatan_jenis_{$i}"] = $row->jenis;
            }
            if ($row->jumlah !== null) {
                $replacements["peralatan_jumlah_{$i}"] = (string) $row->jumlah;
            }
            if ($row->keterangan) {
                $replacements["peralatan_keterangan_{$i}"] = $row->keterangan;
            }
        }
        if ($peralatan->count() > 0) {
            $replacements['peralatan_total'] = (string) $peralatan->count();
        }

        // Consumable
        $consumable = $laporan->consumable->values();
        foreach ($consumable as $index => $row) {
            $i = $index + 1;
            if ($i > self::MAX_ROWS) break;

            $replacements["consumable_no_{$i}"] = (string) $i;
            if ($row->jenis) {
                $replacements["consumable_jenis_{$i}"] = $row->jenis;
            }
            if ($row->jumlah !== null) {
                $replacements["consumable_jumlah_{$i}"] = (string) $row->jumlah;
            }
            if ($row->keterangan) {
                $replacements["consumable_keterangan_{$i}"] = $row->keterangan;
            }
        }
        if ($consumable->count() > 0) {
            $replacements['consumable_total'] = (string) $consumable->count();
        }

        // Aktivitas
        $aktivitas = $laporan->aktivitas->values();
        foreach ($aktivitas as $index => $row) {
            $i = $index + 1;
            if ($i > self::MAX_ROWS) break;

            $replacements["aktivitas_no_{$i}"] = (string) $i;
            if ($row->kategori) {
                $replacements["aktivitas_kategori_{$i}"] = $row->kategori;
            }
            if ($row->aktivitas) {
                $replacements["aktivitas_aktivitas_{$i}"] = $row->aktivitas;
            }
            if ($row->pic) {
                $replacements["aktivitas_pic_{$i}"] = $row->pic;
            }
        }
        if ($aktivitas->count() > 0) {
            $replacements['aktivitas_total'] = (string) $aktivitas->count();
        }

        // Lampiran
        $lampiran = $laporan->lampiran->filter(fn($l) => $l->isFileCompleted())->values();
        foreach ($lampiran as $index => $item) {
            $i = $index + 1;
            if ($i > self::MAX_LAMPIRAN) break;

            if ($item->file_name) {
                $replacements["lampiran_nama_{$i}"] = $item->file_name;
            }
            if ($item->keterangan) {
                $replacements["lampiran_ket_{$i}"] = $item->keterangan;
            }
        }
        if ($lampiran->count() > 0) {
            $replacements['lampiran_total'] = (string) $lampiran->count();
        }

        // Signature
        if ($laporan->jenisKapal?->galangan?->kota) {
            $replacements['ttd_kota'] = $laporan->jenisKapal->galangan->kota;
        }
        if ($laporan->tanggal_laporan) {
            $replacements['ttd_tanggal'] = $laporan->tanggal_laporan->translatedFormat('d F Y');
        }
        if ($laporan->user?->name) {
            $replacements['ttd_nama'] = $laporan->user->name;
        }

        return $replacements;
    }

    // ────────────────────────────────────────────────────────────────────────────
    // ZIP MANIPULATION
    // ────────────────────────────────────────────────────────────────────────────

    private function replaceInDocument(string $docxPath, array $replacements): void
    {
        $zip = new ZipArchive();
        
        if ($zip->open($docxPath) !== true) {
            throw new \RuntimeException('Gagal membuka file DOCX sebagai ZIP');
        }

        // Read document.xml
        $documentXml = $zip->getFromName('word/document.xml');
        if ($documentXml === false) {
            $zip->close();
            throw new \RuntimeException('Gagal membaca word/document.xml dari DOCX');
        }

        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            // Escape XML special characters
            $value = htmlspecialchars($value, ENT_XML1, 'UTF-8');
            // Replace ${placeholder} with value
            $documentXml = str_replace('${' . $placeholder . '}', $value, $documentXml);
        }

        // Write back to ZIP
        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
    }

    // ────────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────────────────────

    private function prepareOutputPath(Laporan $laporan): string
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

        return $dir . '/' . $filename;
    }

    private function getRelativePath(string $fullPath): string
    {
        $appPath = storage_path('app/');
        return str_replace($appPath, '', $fullPath);
    }
}
