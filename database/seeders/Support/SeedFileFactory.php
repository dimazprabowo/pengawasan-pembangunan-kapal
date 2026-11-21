<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Helper bersama untuk seeder: membuat file lampiran NYATA di disk default
 * (WebP via Intervention, PDF via Dompdf) sehingga data contoh terlihat
 * seperti diisi manusia — preview/download bekerja end-to-end.
 */
class SeedFileFactory
{
    private const PHOTO_COLORS = [
        '#3b82f6', '#10b981', '#f59e0b', '#6366f1', '#ef4444',
        '#0ea5e9', '#8b5cf6', '#14b8a6', '#f97316', '#64748b',
    ];

    /**
     * Simpan foto dokumentasi (WebP) ke disk, return [path, size].
     * Warna divariasikan deterministik per $seed agar lampiran terlihat beda.
     */
    public static function storeImage(string $directory, string $seed): array
    {
        $path = $directory.'/'.Str::uuid().'.webp';

        $color = self::PHOTO_COLORS[crc32($seed) % count(self::PHOTO_COLORS)];
        $accent = self::PHOTO_COLORS[(crc32($seed) + 3) % count(self::PHOTO_COLORS)];

        $image = ImageManager::gd()->create(1280, 960)->fill($color);
        // Variasi bentuk sederhana supaya tiap foto tidak identik.
        $image->drawRectangle(80, 80, function ($rect) use ($accent) {
            $rect->size(1120, 800)->border($accent, 12);
        });

        $encoded = (string) $image->toWebp(85);
        Storage::disk()->put($path, $encoded);

        return [$path, strlen($encoded)];
    }

    /**
     * Simpan dokumen PDF nyata (hasil render Dompdf) ke disk,
     * return [path, size]. $sections = [['heading' => ..., 'lines' => [...]]].
     */
    public static function storePdf(string $directory, string $title, array $sections): array
    {
        $path = $directory.'/'.Str::uuid().'.pdf';

        // PDF minimal valid (1 halaman, teks Helvetica) — dibuat manual agar
        // seeding puluhan dokumen ringan memori, tanpa dependensi renderer.
        $content = self::buildMinimalPdf($title, $sections);
        Storage::disk()->put($path, $content);

        return [$path, strlen($content)];
    }

    /**
     * Susun PDF satu halaman sederhana: judul + heading + baris teks.
     * Menghasilkan struktur objek PDF valid agar bisa di-preview/di-download.
     */
    private static function buildMinimalPdf(string $title, array $sections): string
    {
        $esc = fn (string $s) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8'));

        $ops = "BT /F1 16 Tf 50 780 Td ({$esc($title)}) Tj ET\n";
        $y = 750;
        foreach ($sections as $section) {
            $ops .= "BT /F2 12 Tf 50 {$y} Td ({$esc($section['heading'])}) Tj ET\n";
            $y -= 18;
            foreach ($section['lines'] as $line) {
                $ops .= "BT /F1 10 Tf 60 {$y} Td ({$esc('- '.$line)}) Tj ET\n";
                $y -= 14;
            }
            $y -= 6;
        }

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            6 => '<< /Length '.strlen($ops)." >>\nstream\n{$ops}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
