<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Symfony\Component\Process\Process;

class PdfPageService
{
    public function render(string $source, string $directory = 'laporan-nc/pages'): array
    {
        $memoryLimit = ini_parse_quantity(ini_get('memory_limit'));
        if ($memoryLimit > 0 && $memoryLimit < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
        $deadline = microtime(true) + 45;
        $disk = Storage::disk('local');
        $info = new Process([config('services.poppler.pdfinfo'), $disk->path($source)], env: ['LC_ALL' => 'C']);
        $info->setTimeout(10);
        $info->mustRun();
        if (! preg_match('/^Pages:\s+(\d+)/m', $info->getOutput(), $match)) {
            throw new RuntimeException('Jumlah halaman PDF tidak dapat dibaca.');
        }
        $count = (int) $match[1];
        if ($count < 1 || $count > config('services.poppler.max_pages')) {
            throw new RuntimeException('Jumlah halaman PDF melebihi batas pemrosesan.');
        }

        $directory = trim($directory, '/').'/'.Str::uuid();
        $disk->makeDirectory($directory);
        $pages = [];
        $manager = new ImageManager(new Driver);
        try {
            for ($page = 1; $page <= $count; $page++) {
                $prefix = $disk->path($directory.'/page-'.$page);
                $process = new Process([
                    config('services.poppler.pdftoppm'), '-f', (string) $page, '-l', (string) $page,
                    '-singlefile', '-scale-to', '2400', '-png', $disk->path($source), $prefix,
                ]);
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) {
                    throw new RuntimeException('Batas waktu pemrosesan PDF terlampaui.');
                }
                $process->setTimeout(min(15, $remaining));
                $process->mustRun();
                $image = $manager->read($prefix.'.png');
                $path = $directory.'/page-'.$page.'.webp';
                if (! $disk->put($path, (string) $image->toWebp(85))) {
                    throw new RuntimeException('Gagal menyimpan halaman PDF.');
                }
                unset($image);
                $disk->delete($directory.'/page-'.$page.'.png');
                $pages[] = $path;
            }

            return $pages;
        } catch (\Throwable $e) {
            $disk->deleteDirectory($directory);
            throw $e;
        }
    }
}
