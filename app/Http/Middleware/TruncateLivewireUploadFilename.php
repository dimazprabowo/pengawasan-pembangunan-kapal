<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Livewire embeds the base64-encoded original filename into the generated
 * temporary filename (see TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded).
 * Photos from phones/WhatsApp often have very long original names, which can push the
 * generated temp path past OS path length limits (esp. Windows MAX_PATH = 260 chars).
 * When that happens the temp file fails to persist correctly and Livewire is unable to
 * re-hydrate the property as a TemporaryUploadedFile on the next request — it silently
 * degrades to a plain string, causing "Call to a member function getClientOriginalName()
 * on string" when the form is saved.
 *
 * This middleware truncates long original filenames on the Livewire upload endpoint
 * before Livewire generates the temp filename, preventing the path-too-long failure.
 */
class TruncateLivewireUploadFilename
{
    private const MAX_FILENAME_LENGTH = 80;

    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->allFiles() as $key => $file) {
            $this->processFiles($file);
        }

        return $next($request);
    }

    private function processFiles(mixed $file): void
    {
        if (is_array($file)) {
            foreach ($file as $subFile) {
                $this->processFiles($subFile);
            }
            return;
        }

        if ($file instanceof UploadedFile) {
            $this->truncateFilenameIfNeeded($file);
        }
    }

    private function truncateFilenameIfNeeded(UploadedFile $file): void
    {
        $originalName = $file->getClientOriginalName();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $basename = pathinfo($originalName, PATHINFO_FILENAME);

        if (mb_strlen($basename) <= self::MAX_FILENAME_LENGTH) {
            return;
        }

        $truncatedBasename = mb_substr($basename, 0, self::MAX_FILENAME_LENGTH);
        $newFilename = $extension ? "{$truncatedBasename}.{$extension}" : $truncatedBasename;

        $reflection = new \ReflectionClass(\Symfony\Component\HttpFoundation\File\UploadedFile::class);

        if ($reflection->hasProperty('originalName')) {
            $property = $reflection->getProperty('originalName');
            $property->setAccessible(true);
            $property->setValue($file, $newFilename);
        }
    }
}
