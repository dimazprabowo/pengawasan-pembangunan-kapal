<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class FileStorageService
{
    public function storeTemp(UploadedFile $file, string $directory): string
    {
        $this->validatePath($directory);
        $path = $file->store('temp/'.$directory);
        if (! $path) {
            throw new RuntimeException('Gagal menyimpan file sementara.');
        }

        return $path;
    }

    public function storeProcessed(string $directory, string $content, string $originalName, string $extension): array
    {
        $path = $this->buildPath($directory, $extension);
        try {
            if (! Storage::disk()->put($path, $content)) {
                throw new RuntimeException('Gagal menyimpan file hasil pemrosesan.');
            }
        } catch (\Throwable $e) {
            $this->deleteFiles([$path]);
            throw $e;
        }

        return $this->metadata($path, $originalName, $extension, strlen($content));
    }

    public function copyFromTemp(string $tempPath, string $directory, string $originalName, string $extension): array
    {
        $this->validateTempPath($tempPath);
        $disk = Storage::disk();
        if (! $disk->exists($tempPath)) {
            throw new RuntimeException('File sementara tidak ditemukan.');
        }
        $path = $this->buildPath($directory, $extension);
        try {
            if (! $disk->copy($tempPath, $path)) {
                throw new RuntimeException('Gagal menyimpan salinan file.');
            }
            $size = $disk->size($path);
        } catch (\Throwable $e) {
            $this->deleteFiles([$path]);
            throw $e;
        }

        return $this->metadata($path, $originalName, $extension, $size);
    }

    public function deleteFiles(array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($path) => $path !== null && $path !== '')));
        foreach ($paths as $path) {
            $this->validatePath($path);
        }
        if ($paths !== [] && ! Storage::disk()->delete($paths)) {
            throw new RuntimeException('Gagal membersihkan file.');
        }
    }

    public function deleteTemp(string $path): void
    {
        $this->validateTempPath($path);
        $this->deleteFiles([$path]);
    }

    private function buildPath(string $directory, string $extension): string
    {
        $this->validatePath($directory);
        if (! preg_match('/\A[a-z0-9]+\z/', $extension)) {
            throw new InvalidArgumentException('Ekstensi file tidak valid.');
        }

        return $directory.'/'.Str::uuid().'.'.$extension;
    }

    private function metadata(string $path, string $originalName, string $extension, int $size): array
    {
        $name = pathinfo(basename(str_replace('\\', '/', $originalName)), PATHINFO_FILENAME);

        return ['path' => $path, 'name' => ($name ?: 'file').'.'.$extension, 'size' => $size];
    }

    private function validateTempPath(string $path): void
    {
        $this->validatePath($path);
        if (! str_starts_with($path, 'temp/')) {
            throw new InvalidArgumentException('Path bukan file sementara aplikasi.');
        }
    }

    private function validatePath(string $path): void
    {
        if (! preg_match('/\A[a-zA-Z0-9_-]+(?:\/[a-zA-Z0-9_.-]+)*\z/', $path)
            || in_array('..', explode('/', $path), true) || in_array('.', explode('/', $path), true)) {
            throw new InvalidArgumentException('Path penyimpanan tidak valid.');
        }
    }
}
