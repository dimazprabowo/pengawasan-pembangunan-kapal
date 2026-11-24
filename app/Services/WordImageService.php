<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class WordImageService
{
    // Default image dimensions for Word documents
    public const DEFAULT_WIDTH = 200;

    public const DEFAULT_HEIGHT = 150;

    public const DEFAULT_QUALITY = 50;

    /**
     * Prepare an image for insertion into a Word document.
     * Resizes and compresses the image to reduce file size.
     *
     * @param  string  $imagePath  Full path to the image file
     * @param  int  $targetWidth  Target width in pixels (default: self::DEFAULT_WIDTH)
     * @param  int  $targetHeight  Target height in pixels (default: self::DEFAULT_HEIGHT)
     * @param  int  $quality  JPEG quality 0-100 (default: self::DEFAULT_QUALITY)
     * @return string Path to the processed image (temporary file if converted)
     */
    public function prepareImageForWord(string $imagePath, ?int $targetWidth = null, ?int $targetHeight = null, ?int $quality = null): string
    {
        $targetWidth = $targetWidth ?? self::DEFAULT_WIDTH;
        $targetHeight = $targetHeight ?? self::DEFAULT_HEIGHT;
        $quality = $quality ?? self::DEFAULT_QUALITY;

        try {
            $manager = new ImageManager(new Driver);
            $image = $manager->read($imagePath);

            // Resize image to target dimensions
            $image->scaleDown($targetWidth, $targetHeight);

            $tmp = sys_get_temp_dir().'/'.uniqid('word_img_').'.jpg';

            // Convert to JPEG with specified quality for compression
            $image->toJpeg($quality)->save($tmp);

            return $tmp;
        } catch (\Exception $e) {
            Log::error('WordImageService: failed prepare image', ['error' => $e->getMessage()]);

            return $imagePath;
        }
    }

    /**
     * Kotak konten (non-putih) gambar halaman, dalam fraksi 0..1
     * [left, top, right, bottom], atau null bila halaman kosong/gagal dibaca.
     * Dipakai menghitung crop SERAGAM per dokumen sumber — crop per halaman
     * menggeser halaman yang isinya sedikit (mis. sampul) ke kiri-atas.
     *
     * @return array{0: float, 1: float, 2: float, 3: float}|null
     */
    public function contentBounds(string $imagePath, int $threshold = 242): ?array
    {
        try {
            $probe = (new ImageManager(new Driver))->read($imagePath)->scaleDown(400, 400);
            $gd = $probe->core()->native();
            $pw = imagesx($gd);
            $ph = imagesy($gd);
            $trueColor = imageistruecolor($gd);
            $minX = $pw;
            $minY = $ph;
            $maxX = -1;
            $maxY = -1;
            for ($y = 0; $y < $ph; $y++) {
                for ($x = 0; $x < $pw; $x++) {
                    $rgb = imagecolorat($gd, $x, $y);
                    if ($trueColor) {
                        $lum = min(($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF);
                    } else {
                        $c = imagecolorsforindex($gd, $rgb);
                        $lum = min($c['red'], $c['green'], $c['blue']);
                    }
                    if ($lum < $threshold) {
                        $minX = min($minX, $x);
                        $maxX = max($maxX, $x);
                        $minY = min($minY, $y);
                        $maxY = max($maxY, $y);
                    }
                }
            }

            return $maxX < 0 ? null : [$minX / $pw, $minY / $ph, ($maxX + 1) / $pw, ($maxY + 1) / $ph];
        } catch (\Throwable $e) {
            Log::warning('WordImageService: gagal membaca kotak konten', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Crop gambar ke kotak fraksi $bounds (+ padding tipis). Mengembalikan
     * path temp baru, atau path asal bila crop tidak berarti / gagal.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bounds
     */
    public function cropToBounds(string $imagePath, array $bounds): string
    {
        try {
            $image = (new ImageManager(new Driver))->read($imagePath);
            $w = $image->width();
            $h = $image->height();
            $pad = (int) round(max($w, $h) * 0.008);
            $left = max(0, (int) floor($bounds[0] * $w) - $pad);
            $top = max(0, (int) floor($bounds[1] * $h) - $pad);
            $cw = min($w, (int) ceil($bounds[2] * $w) + $pad) - $left;
            $ch = min($h, (int) ceil($bounds[3] * $h) + $pad) - $top;
            if ($cw < 1 || $ch < 1 || ($cw >= $w - 8 && $ch >= $h - 8)) {
                return $imagePath;
            }

            $image->crop($cw, $ch, $left, $top);
            $tmp = sys_get_temp_dir().'/'.uniqid('word_img_trim_').'.jpg';
            $image->toJpeg(88)->save($tmp);
            unset($image);

            return $tmp;
        } catch (\Throwable $e) {
            Log::warning('WordImageService: gagal crop margin', ['error' => $e->getMessage()]);

            return $imagePath;
        }
    }

    /**
     * Check if a file is an image that can be processed.
     */
    public function isImageFile(?string $filePath): bool
    {
        if (! $filePath) {
            return false;
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
    }

    /**
     * Resolve path lokal untuk file lampiran pada disk terkonfigurasi.
     * Pada driver non-lokal mengembalikan salinan temp (dibersihkan otomatis).
     */
    public function resolveLampiranPath(?string $filePath): ?string
    {
        if (! $filePath) {
            return null;
        }

        $files = app(FilePathService::class);

        return $files->exists($filePath) ? $files->localPath($filePath) : null;
    }
}
