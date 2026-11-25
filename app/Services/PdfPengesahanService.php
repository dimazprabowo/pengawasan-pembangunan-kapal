<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Ganti halaman "LEMBAR PENGESAHAN" pada PDF rilis laporan bulanan dengan
 * PDF tanda tangan basah yang diupload pengguna. Memakai poppler-utils
 * (pdftotext/pdfseparate/pdfunite) yang sudah menjadi dependency aplikasi.
 */
class PdfPengesahanService
{
    /**
     * Tulis ulang $targetPdf menjadi: halaman 1..n-1 + seluruh halaman
     * $pengesahanPdf + halaman n+1..akhir, dengan n = halaman yang memuat
     * teks "LEMBAR PENGESAHAN".
     */
    public function replace(string $targetPdf, string $pengesahanPdf): void
    {
        if (! is_file($targetPdf) || ! is_file($pengesahanPdf)) {
            throw new RuntimeException('File PDF untuk penggabungan tidak ditemukan.');
        }

        $pages = $this->pageCount($targetPdf);
        $pengesahanPage = $this->findPengesahanPage($targetPdf, $pages);
        if ($pengesahanPage === null) {
            throw new RuntimeException('Halaman LEMBAR PENGESAHAN tidak ditemukan pada PDF rilis.');
        }

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('pengesahan-', true);
        if (! mkdir($dir, 0775, true)) {
            throw new RuntimeException('Gagal membuat direktori sementara PDF.');
        }

        try {
            $parts = [];
            if ($pengesahanPage > 1) {
                $this->separate($targetPdf, 1, $pengesahanPage - 1, $dir);
                $parts = array_merge($parts, $this->collectedPages($dir, 1, $pengesahanPage - 1));
            }
            $parts[] = $pengesahanPdf;
            if ($pengesahanPage < $pages) {
                $this->separate($targetPdf, $pengesahanPage + 1, $pages, $dir, 'after');
                $parts = array_merge($parts, $this->collectedPages($dir, $pengesahanPage + 1, $pages, 'after'));
            }

            $merged = $dir.DIRECTORY_SEPARATOR.'merged.pdf';
            $this->unite($parts, $merged);
            if (! copy($merged, $targetPdf)) {
                throw new RuntimeException('Gagal menimpa PDF rilis dengan lembar pengesahan.');
            }
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function pageCount(string $pdf): int
    {
        $info = new Process([config('services.poppler.pdfinfo'), $pdf], env: ['LC_ALL' => 'C']);
        $info->setTimeout(15);
        $info->mustRun();
        if (! preg_match('/^Pages:\s+(\d+)/m', $info->getOutput(), $match)) {
            throw new RuntimeException('Jumlah halaman PDF tidak dapat dibaca.');
        }

        return (int) $match[1];
    }

    /**
     * Cari nomor halaman (1-based) pertama yang memuat teks LEMBAR
     * PENGESAHAN. Judul halaman lain juga mengandung kata "PENGESAHAN"
     * lewat TOC — karena itu pencarian dibatasi halaman awal dokumen
     * (sebelum BAB, halaman pengesahan selalu mendahului daftar isi).
     */
    private function findPengesahanPage(string $pdf, int $pages): ?int
    {
        $limit = min($pages, 10);
        for ($page = 1; $page <= $limit; $page++) {
            $text = new Process([
                config('services.poppler.pdftotext'), '-f', (string) $page, '-l', (string) $page, $pdf, '-',
            ]);
            $text->setTimeout(15);
            $text->run();
            $output = preg_replace('/\s+/u', ' ', $text->getOutput());
            if (str_contains(mb_strtoupper((string) $output), 'LEMBAR PENGESAHAN')) {
                return $page;
            }
            // TOC memuat entri "LEMBAR PENGESAHAN" — halaman yang juga
            // memuat "KATA PENGANTAR"/"BAB" adalah daftar isi, bukan
            // halaman pengesahan.
        }

        return null;
    }

    private function separate(string $pdf, int $from, int $to, string $dir, string $prefix = 'part'): void
    {
        $process = new Process([
            config('services.poppler.pdfseparate'), '-f', (string) $from, '-l', (string) $to,
            $pdf, $dir.DIRECTORY_SEPARATOR.$prefix.'-%d.pdf',
        ]);
        $process->setTimeout(120);
        $process->mustRun();
    }

    /**
     * @return string[] path halaman terpisah urut nomor halaman
     */
    private function collectedPages(string $dir, int $from, int $to, string $prefix = 'part'): array
    {
        $pages = [];
        for ($page = $from; $page <= $to; $page++) {
            $path = $dir.DIRECTORY_SEPARATOR.$prefix.'-'.$page.'.pdf';
            if (! is_file($path)) {
                throw new RuntimeException('Pemisahan halaman PDF tidak lengkap.');
            }
            $pages[] = $path;
        }

        return $pages;
    }

    private function unite(array $parts, string $output): void
    {
        $level = 0;
        while (count($parts) > 20) {
            $merged = [];
            foreach (array_chunk($parts, 20) as $index => $batch) {
                if (count($batch) === 1) {
                    $merged[] = $batch[0];

                    continue;
                }
                $part = dirname($output).DIRECTORY_SEPARATOR.'batch-'.$level.'-'.$index.'.pdf';
                $this->unite($batch, $part);
                $merged[] = $part;
            }
            $parts = $merged;
            $level++;
        }

        $process = new Process([...array_map('strval', array_merge([config('services.poppler.pdfunite')], $parts, [$output]))]);
        $process->setTimeout(180);
        $process->mustRun();
    }
}
