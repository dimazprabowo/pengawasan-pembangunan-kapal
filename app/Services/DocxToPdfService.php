<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;

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
        $this->lastError = null;
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
        $binary = $this->libreOfficeBinary();

        return $binary !== null && (new ExecutableFinder)->find($binary) !== null;
    }

    private function libreOfficeBinary(): ?string
    {
        $binary = config('services.libreoffice.binary', env('SOFFICE_BINARY', 'soffice'));

        return $binary && trim((string) $binary) !== '' ? (string) $binary : null;
    }

    protected function convertViaWord(string $source, string $target): bool
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

        $workingDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'simpro-convert-'.Str::uuid();
        $outDir = $workingDir.DIRECTORY_SEPARATOR.'output';
        $profile = $workingDir.DIRECTORY_SEPARATOR.'profile';

        try {
            File::ensureDirectoryExists($outDir, 0700);
            File::ensureDirectoryExists($profile, 0700);
            $profileUri = 'file:///'.implode('/', array_map('rawurlencode', explode('/', ltrim(str_replace('\\', '/', $profile), '/'))));
            $profileUri = str_replace('%3A', ':', $profileUri);
            $process = Process::timeout(180)->run([
                $binary, '-env:UserInstallation='.$profileUri, '--headless', '--norestore',
                '--convert-to', 'pdf:writer_pdf_Export', '--outdir', $outDir, $source,
            ]);
            if (! $process->successful()) {
                throw new RuntimeException('Proses konversi keluar dengan kode '.($process->exitCode() ?? 'tidak diketahui').'.');
            }

            // LibreOffice menamai output mengikuti nama file sumber.
            $produced = $outDir.DIRECTORY_SEPARATOR.pathinfo($source, PATHINFO_FILENAME).'.pdf';
            if (! is_file($produced) || filesize($produced) === 0) {
                throw new RuntimeException('Proses konversi tidak menghasilkan PDF.');
            }
            File::ensureDirectoryExists(dirname($target));
            if (! copy($produced, $target)) {
                throw new RuntimeException('Hasil konversi PDF gagal disimpan.');
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError ??= 'LibreOffice: '.$e->getMessage();

            return false;
        } finally {
            File::deleteDirectory($workingDir);
        }
    }
}
