<?php

namespace Tests\Feature;

use App\Services\DocxToPdfService;
use App\Services\WordStyleService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class DocxToPdfServiceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'converter-test-'.Str::uuid();
        File::ensureDirectoryExists($this->directory);
        file_put_contents($this->directory.'/source.docx', 'source');
        config(['services.libreoffice.binary' => PHP_BINARY]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function converter(): DocxToPdfService
    {
        return new class extends DocxToPdfService
        {
            protected function convertViaWord(string $source, string $target): bool
            {
                return false;
            }
        };
    }

    #[Test]
    public function libreoffice_uses_unique_profiles_and_outputs_and_cleans_up(): void
    {
        $directories = [];
        Process::fake(function ($process) use (&$directories) {
            $command = $process->command;
            $this->assertStringStartsWith('-env:UserInstallation=file:///', $command[1]);
            $output = $command[array_search('--outdir', $command, true) + 1];
            $directories[] = dirname($output);
            file_put_contents($output.'/source.pdf', '%PDF-1.4 test');

            return Process::result();
        });
        $converter = $this->converter();
        foreach (['one', 'two'] as $name) {
            $converter->convert($this->directory.'/source.docx', $this->directory.'/'.$name.'.pdf');
            $this->assertSame('%PDF-1.4 test', file_get_contents($this->directory.'/'.$name.'.pdf'));
        }
        $this->assertNotSame($directories[0], $directories[1]);
        foreach ($directories as $directory) {
            $this->assertDirectoryDoesNotExist($directory);
        }
    }

    #[Test]
    public function failed_or_empty_conversion_never_accepts_an_old_target(): void
    {
        file_put_contents($this->directory.'/existing.pdf', 'old PDF');
        foreach ([0, 1] as $exitCode) {
            Process::fake(fn () => Process::result(exitCode: $exitCode));
            try {
                $this->converter()->convert($this->directory.'/source.docx', $this->directory.'/existing.pdf');
                $this->fail('Conversion must fail without a fresh output.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Konversi PDF gagal', $e->getMessage());
            }
            $this->assertSame('old PDF', file_get_contents($this->directory.'/existing.pdf'));
        }
    }

    #[Test]
    public function linux_libreoffice_can_convert_a_real_docx(): void
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->markTestSkipped('Integration converter Linux dijalankan pada image Docker.');
        }
        config(['services.libreoffice.binary' => 'soffice']);
        if (! $this->converter()->isAvailable()) {
            $this->markTestSkipped('LibreOffice tidak terpasang.');
        }
        $document = new PhpWord;
        $document->addSection()->addText('Dokumen pendukung SIMPRO');
        WordStyleService::prepare($document);
        IOFactory::createWriter($document, 'Word2007')->save($this->directory.'/source.docx');
        $this->converter()->convert($this->directory.'/source.docx', $this->directory.'/actual.pdf');
        $this->assertStringStartsWith('%PDF-', file_get_contents($this->directory.'/actual.pdf'));
    }
}
