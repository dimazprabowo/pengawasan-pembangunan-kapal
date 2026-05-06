<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

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
     * @param string $imagePath Full path to the image file
     * @param int $targetWidth Target width in pixels (default: self::DEFAULT_WIDTH)
     * @param int $targetHeight Target height in pixels (default: self::DEFAULT_HEIGHT)
     * @param int $quality JPEG quality 0-100 (default: self::DEFAULT_QUALITY)
     * @return string Path to the processed image (temporary file if converted)
     */
    public function prepareImageForWord(string $imagePath, int $targetWidth = null, int $targetHeight = null, int $quality = null): string
    {
        $targetWidth = $targetWidth ?? self::DEFAULT_WIDTH;
        $targetHeight = $targetHeight ?? self::DEFAULT_HEIGHT;
        $quality = $quality ?? self::DEFAULT_QUALITY;

        try {
            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $image   = $manager->read($imagePath);

            // Resize image to target dimensions
            $image->scaleDown($targetWidth, $targetHeight);

            $tmp = sys_get_temp_dir() . '/' . uniqid('word_img_') . '.jpg';

            // Convert to JPEG with specified quality for compression
            $image->toJpeg($quality)->save($tmp);

            return $tmp;
        } catch (\Exception $e) {
            Log::error('WordImageService: failed prepare image', ['error' => $e->getMessage()]);
            return $imagePath;
        }
    }

    /**
     * Check if a file is an image that can be processed.
     *
     * @param string|null $filePath
     * @return bool
     */
    public function isImageFile(?string $filePath): bool
    {
        if (!$filePath) {
            return false;
        }
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp']);
    }

    /**
     * Resolve the full path to a lampiran file.
     * Checks both private storage and direct storage paths.
     *
     * @param string|null $filePath
     * @return string|null
     */
    public function resolveLampiranPath(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }

        $private = storage_path('app/private/' . $filePath);
        if (file_exists($private)) {
            return $private;
        }

        $direct = storage_path('app/' . $filePath);
        return file_exists($direct) ? $direct : null;
    }
}
