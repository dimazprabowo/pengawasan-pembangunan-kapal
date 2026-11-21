<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Akses file pada disk default (FILESYSTEM_DISK — local ataupun s3).
 *
 * Tool native (PhpWord, ZipArchive, Word COM, pdftoppm, Intervention) hanya bisa
 * bekerja dengan path filesystem lokal, sedangkan file artefak disimpan pada
 * disk terkonfigurasi. Service ini menjembatani:
 * - localPath(): path absolut untuk MEMBACA (disk local -> path asli; driver
 *   lain -> salinan temp lokal yang dibersihkan saat shutdown/cleanupTemp()).
 * - tempPath(): path lokal kosong untuk MENULIS hasil tool native.
 * - commitLocalFile(): unggah file lokal hasil tool ke disk tujuan.
 */
class FilePathService
{
    /** @var list<string> */
    private array $tempFiles = [];

    private bool $shutdownHooked = false;

    public function disk(): Filesystem
    {
        return Storage::disk();
    }

    public function isLocalDisk(): bool
    {
        $default = (string) config('filesystems.default');

        return config('filesystems.disks.'.$default.'.driver') === 'local';
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * @param  string|list<string>  $paths
     */
    public function delete(string|array $paths): void
    {
        $this->disk()->delete($paths);
    }

    public function put(string $path, string $contents): void
    {
        if (! $this->disk()->put($path, $contents)) {
            throw new RuntimeException('Gagal menyimpan file.');
        }
    }

    /**
     * Respons download agnostik driver (lokal maupun s3).
     */
    public function download(string $path, string $name, array $headers = [])
    {
        return $this->disk()->download($path, $name, $headers);
    }

    /**
     * Path absolut yang DIJAMIN bisa dibaca tool native.
     * Pada disk local mengembalikan path asli file; pada driver lain mengunduh
     * ke file temp lokal (dibersihkan otomatis saat shutdown/cleanupTemp()).
     */
    public function localPath(string $path): string
    {
        if ($this->isLocalDisk()) {
            return $this->disk()->path($path);
        }

        $stream = $this->disk()->readStream($path);
        if ($stream === false) {
            throw new RuntimeException('File tidak ditemukan: '.$path);
        }

        $temp = $this->tempPath(pathinfo($path, PATHINFO_EXTENSION) ?: 'tmp');
        file_put_contents($temp, $stream);
        fclose($stream);

        return $temp;
    }

    /**
     * Path lokal kosong untuk output tool native (docx/pdf/png sementara)
     * sebelum di-commit ke disk via commitLocalFile().
     */
    public function tempPath(string $extension): string
    {
        $dir = storage_path('app/tmp-files');
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('Gagal membuat direktori file sementara.');
        }

        $path = $dir.DIRECTORY_SEPARATOR.Str::uuid().'.'.$extension;
        $this->registerTemp($path);

        return $path;
    }

    /**
     * Simpan file lokal hasil tool native ke disk tujuan.
     */
    public function commitLocalFile(string $localFile, string $path): void
    {
        $stream = fopen($localFile, 'rb');
        if ($stream === false) {
            throw new RuntimeException('File hasil pemrosesan tidak terbaca.');
        }

        try {
            if (! $this->disk()->put($path, $stream)) {
                throw new RuntimeException('Gagal menyimpan file.');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * Hapus file temp yang dibuat instance ini. Aman dipanggil berulang.
     */
    public function cleanupTemp(): void
    {
        foreach ($this->tempFiles as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];
    }

    public function registerTemp(string $path): void
    {
        $this->tempFiles[] = $path;

        if (! $this->shutdownHooked) {
            $this->shutdownHooked = true;
            register_shutdown_function(function () {
                $this->cleanupTemp();
            });
        }
    }
}
