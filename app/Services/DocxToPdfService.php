<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Konversi DOCX → PDF untuk fitur Release laporan bulanan.
 *
 * Driver utama: Microsoft Word COM (Windows + MS Word terinstall) —
 * render paling akurat dan sekaligus memperbarui field TOC sebelum export.
 * Fallback: LibreOffice headless (`soffice`, binary via env SOFFICE_BINARY) —
 * gratis dan berjalan di Linux; sejak lampiran rantai disisipkan sebagai
 * gambar halaman (bukan altChunk), keduanya mampu merender lampiran.
 */
class DocxToPdfService
{
    /**
     * wdFormatPDF — konstanta Word untuk SaveAs/ExportAsFixedFormat.
     */
    private const WD_FORMAT_PDF = 17;

    private ?string $lastError = null;

    public function convert(string $docxAbsolutePath, string $pdfAbsolutePath): void
    {
        if (! is_file($docxAbsolutePath)) {
            throw new RuntimeException('File dokumen Word tidak ditemukan.');
        }

        if ($this->convertViaWord($docxAbsolutePath, $pdfAbsolutePath)) {
            return;
        }

        if ($this->convertViaLibreOffice($docxAbsolutePath, $pdfAbsolutePath)) {
            return;
        }

        // Engine ada tetapi konversi gagal → sertakan penyebab asli agar
        // pdf_error informatif; tanpa engine sama sekali → pesan ketersediaan.
        if ($this->lastError !== null) {
            throw new RuntimeException('Konversi PDF gagal: '.$this->lastError);
        }

        throw new RuntimeException(
            'Konversi PDF tidak tersedia: pasang Microsoft Word (Windows) atau LibreOffice (atur SOFFICE_BINARY).'
        );
    }

    /**
     * Apakah minimal satu engine konversi tersedia di environment ini.
     */
    public function isAvailable(): bool
    {
        return $this->wordComAvailable() || $this->libreOfficeAvailable();
    }

    private function wordComAvailable(): bool
    {
        return PHP_OS_FAMILY === 'Windows' && class_exists(\COM::class);
    }

    private function libreOfficeAvailable(): bool
    {
        return $this->libreOfficeBinary() !== null;
    }

    private function libreOfficeBinary(): ?string
    {
        $binary = config('services.libreoffice.binary', env('SOFFICE_BINARY', 'soffice'));

        return $binary && trim((string) $binary) !== '' ? (string) $binary : null;
    }

    private function convertViaWord(string $source, string $target): bool
    {
        if (! $this->wordComAvailable()) {
            return false;
        }

        $word = null;
        $doc = null;

        try {
            $word = new \COM('Word.Application');
            $word->Visible = false;
            $word->DisplayAlerts = 0; // wdAlertsNone

            $doc = $word->Documents->Open($source, false, true);

            // Perbarui TOC/field agar nomor halaman akurat sebelum export.
            try {
                $doc->Fields->Update();
                $toc = $doc->TablesOfContents;
                for ($i = 1; $i <= $toc->Count; $i++) {
                    $toc->Item($i)->Update();
                }
                $doc->Repaginate();
            } catch (\Throwable) {
                // Pembaruan field best-effort — export tetap dilanjutkan.
            }

            // ExportAsFixedFormat(namaFile, wdExportFormatPDF) — SaveAs dengan
            // argumen array gagal "Type mismatch" pada binding COM PHP.
            $doc->ExportAsFixedFormat($target, self::WD_FORMAT_PDF);

            return is_file($target) && filesize($target) > 0;
        } catch (\Throwable $e) {
            $this->lastError = 'MS Word: '.$e->getMessage();

            return false;
        } finally {
            try {
                $doc?->Close(false);
            } catch (\Throwable) {
            }
            try {
                $word?->Quit();
            } catch (\Throwable) {
            }
        }
    }

    private function convertViaLibreOffice(string $source, string $target): bool
    {
        $binary = $this->libreOfficeBinary();
        if ($binary === null) {
            return false;
        }

        $outDir = dirname($target);
        if (! is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }

        try {
            $process = new Process([
                $binary, '--headless', '--norestore', '--convert-to', 'pdf', '--outdir', $outDir, $source,
            ]);
            $process->setTimeout(180);
            $process->run();

            // LibreOffice menamai output mengikuti nama file sumber.
            $produced = $outDir.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf';
            if (is_file($produced) && $produced !== $target) {
                rename($produced, $target);
            }
        } catch (\Throwable $e) {
            $this->lastError ??= 'LibreOffice: '.$e->getMessage();

            return false;
        }

        return is_file($target) && filesize($target) > 0;
    }
}
