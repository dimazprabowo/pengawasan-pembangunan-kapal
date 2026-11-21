<?php

namespace Tests\Feature;

use App\Services\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FileStorageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function processed_files_have_unique_paths_and_server_selected_extensions(): void
    {
        $service = app(FileStorageService::class);
        $first = $service->storeProcessed('laporan-nc/foto', 'converted', '../../foto.php', 'webp');
        $second = $service->storeProcessed('laporan-nc/foto', 'converted', '../../foto.php', 'webp');

        $this->assertNotSame($first['path'], $second['path']);
        $this->assertStringStartsWith('laporan-nc/foto/', $first['path']);
        $this->assertStringEndsWith('.webp', $first['path']);
        $this->assertSame('foto.webp', $first['name']);
        $this->assertSame(9, $first['size']);
        Storage::disk('local')->assertExists([$first['path'], $second['path']]);
    }

    #[Test]
    public function copying_original_keeps_temporary_bytes_until_explicit_cleanup(): void
    {
        $service = app(FileStorageService::class);
        $temp = $service->storeTemp(UploadedFile::fake()->createWithContent('scan.pdf', '%PDF-1.4 signed'), 'laporan-nc-form');
        $result = $service->copyFromTemp($temp, 'laporan-nc/form', 'scan.pdf', 'pdf');

        $this->assertSame('%PDF-1.4 signed', Storage::disk('local')->get($result['path']));
        Storage::disk('local')->assertExists($temp);
        $service->deleteTemp($temp);
        Storage::disk('local')->assertMissing($temp);
        Storage::disk('local')->assertExists($result['path']);
    }

    #[Test]
    public function failed_write_throws_instead_of_returning_completed_metadata(): void
    {
        Storage::shouldReceive('disk')->withNoArgs()->andReturnSelf();
        Storage::shouldReceive('put')->once()->andReturn(false);
        Storage::shouldReceive('delete')->once()->andReturn(true);

        $this->expectException(RuntimeException::class);
        app(FileStorageService::class)->storeProcessed('laporan-nc/foto', 'content', 'foto.png', 'webp');
    }

    #[Test]
    public function traversal_and_deleting_a_permanent_file_as_temp_are_rejected(): void
    {
        Storage::disk('local')->put('laporan-nc/keep.pdf', 'keep');
        try {
            app(FileStorageService::class)->deleteTemp('laporan-nc/keep.pdf');
            $this->fail('A permanent path must not be accepted as a temporary file.');
        } catch (\InvalidArgumentException) {
            Storage::disk('local')->assertExists('laporan-nc/keep.pdf');
        }

        $this->expectException(\InvalidArgumentException::class);
        app(FileStorageService::class)->storeProcessed('../outside', 'content', 'file.pdf', 'pdf');
    }
}
