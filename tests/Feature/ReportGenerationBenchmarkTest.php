<?php

namespace Tests\Feature;

use App\Jobs\GenerateLaporanBulananJob;
use App\Jobs\ReleaseLaporanBulananPdfJob;
use App\Models\LaporanBulanan;
use App\Models\LaporanHarian;
use App\Services\DocxToPdfService;
use App\Services\FilePathService;
use App\Services\LaporanBulananService;
use App\Services\LaporanHarianService;
use App\Services\PdfPageService;
use App\Services\WordAttachmentService;
use App\Services\WordImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReportGenerationBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private string $originalStorage;

    private string $sandbox;

    private object $metrics;

    protected function beforeRefreshingDatabase(): void
    {
        if (getenv('RUN_REPORT_BENCHMARK') !== '1') {
            $this->markTestSkipped('Opt-in: RUN_REPORT_BENCHMARK=1 php artisan test --filter=ReportGenerationBenchmarkTest');
        }
        if (! app()->environment('testing') || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Benchmark hanya boleh memakai database testing SQLite :memory:.');
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertTrue(app(DocxToPdfService::class)->isAvailable(), 'Converter Word/LibreOffice diperlukan.');
        $this->originalStorage = storage_path();
        $this->sandbox = sys_get_temp_dir().DIRECTORY_SEPARATOR.'report-benchmark-'.Str::uuid();
        foreach (['harian', 'mingguan', 'bulanan'] as $type) {
            $directory = $this->sandbox.'/app/private/templates/laporan-'.$type;
            File::ensureDirectoryExists($directory);
            File::copy(base_path('storage/app/private/templates/laporan-'.$type.'/template-laporan-'.$type.'.docx'), $directory.'/template-laporan-'.$type.'.docx');
        }
        app()->useStoragePath($this->sandbox);
        config(['filesystems.default' => 'local', 'filesystems.disks.local.root' => $this->sandbox.'/app/private']);
        Storage::forgetDisk('local');
        $this->seed();
        $this->metrics = (object) ['events' => [], 'phase' => 'word', 'engine' => null, 'queries' => 0, 'query_ms' => 0.0];
        $this->instrumentServices();
        DB::listen(function ($query): void {
            $this->metrics->queries++;
            $this->metrics->query_ms += $query->time;
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->originalStorage)) {
            app(FilePathService::class)->cleanupTemp();
            app()->useStoragePath($this->originalStorage);
            Storage::forgetDisk('local');
            File::deleteDirectory($this->sandbox);
        }
        parent::tearDown();
    }

    private function instrumentServices(): void
    {
        $metrics = $this->metrics;
        app()->instance(DocxToPdfService::class, new class($metrics) extends DocxToPdfService
        {
            public function __construct(private object $metrics) {}

            public function convert(string $docxAbsolutePath, string $pdfAbsolutePath): void
            {
                $start = hrtime(true);
                try {
                    parent::convert($docxAbsolutePath, $pdfAbsolutePath);
                } finally {
                    $this->metrics->events[] = ['stage' => 'docx_pdf', 'phase' => $this->metrics->phase, 'seconds' => (hrtime(true) - $start) / 1e9];
                }
            }

            protected function convertViaWord(string $source, string $target): bool
            {
                $success = parent::convertViaWord($source, $target);
                $this->metrics->engine = $success ? 'Word COM' : 'LibreOffice fallback';

                return $success;
            }
        });
        app()->instance(PdfPageService::class, new class($metrics) extends PdfPageService
        {
            public function __construct(private object $metrics) {}

            public function render(string $source, string $directory = 'laporan-nc/pages'): array
            {
                $start = hrtime(true);
                $pages = parent::render($source, $directory);
                $this->metrics->events[] = ['stage' => 'pdf_images', 'phase' => $this->metrics->phase, 'seconds' => (hrtime(true) - $start) / 1e9, 'pages' => count($pages)];

                return $pages;
            }
        });
        app()->instance(WordImageService::class, new class($metrics) extends WordImageService
        {
            public function __construct(private object $metrics) {}

            public function prepareImageForWord(string $imagePath, ?int $targetWidth = null, ?int $targetHeight = null, ?int $quality = null): string
            {
                $start = hrtime(true);
                $path = parent::prepareImageForWord($imagePath, $targetWidth, $targetHeight, $quality);
                $this->metrics->events[] = ['stage' => 'prepare_image', 'phase' => $this->metrics->phase, 'seconds' => (hrtime(true) - $start) / 1e9];

                return $path;
            }
        });
        app()->instance(WordAttachmentService::class, new class($metrics) extends WordAttachmentService
        {
            public function __construct(private object $metrics) {}

            public function reportPageImages(string $docPath): array
            {
                $before = count(array_filter($this->metrics->events, fn ($event) => $event['stage'] === 'docx_pdf'));
                $pages = parent::reportPageImages($docPath);
                $after = count(array_filter($this->metrics->events, fn ($event) => $event['stage'] === 'docx_pdf'));
                $this->metrics->events[] = ['stage' => 'page_cache', 'phase' => $this->metrics->phase, 'hit' => $before === $after, 'pages' => count($pages)];

                return $pages;
            }
        });
    }

    #[Test]
    public function measures_cold_warm_and_single_daily_invalidation_with_real_converters(): void
    {
        $month = max(1, (int) (getenv('REPORT_BENCHMARK_MONTH') ?: 1));
        $report = LaporanBulanan::query()->where('bulan_ke', $month)->orderBy('id')->firstOrFail();
        $weeks = app(LaporanBulananService::class)->getMingguanBulan($report);
        $daily = LaporanHarian::query()->where('data_kapal_id', $report->data_kapal_id)
            ->whereBetween('tanggal_laporan', [$weeks->min('periode_mulai'), $weeks->max('periode_selesai')])
            ->orderBy('tanggal_laporan')->get();
        $this->assertNotEmpty($weeks);
        $this->assertNotEmpty($daily);
        $this->assertTrue($weeks->every(fn ($week) => $week->doc_path === null));
        $this->assertTrue($daily->every(fn ($day) => $day->doc_path === null));
        fwrite(STDOUT, PHP_EOL.'BENCHMARK_FIXTURE '.json_encode([
            'php' => PHP_VERSION, 'os' => PHP_OS_FAMILY, 'weeks' => $weeks->count(), 'daily' => $daily->count(),
            'period' => [$report->periode_mulai->toDateString(), $report->periode_selesai->toDateString()],
            'external_documents' => $report->laporanExternal()->count() + $weeks->sum(fn ($week) => $week->laporanExternal()->count()),
            'daily_photos' => $daily->sum(fn ($day) => $day->lampiran()->count()),
            'queue_wait_included' => false, 'seeding_included' => false,
        ], JSON_UNESCAPED_SLASHES).PHP_EOL);
        $baselinePages = null;
        foreach (['cold', 'warm', 'one_daily_changed'] as $scenario) {
            if ($scenario === 'one_daily_changed') {
                app(LaporanHarianService::class)->update($daily->first()->fresh(), ['suhu' => (float) $daily->first()->suhu + 0.5]);
            }
            $dailyBefore = LaporanHarian::whereIn('id', $daily->modelKeys())->pluck('doc_path', 'id')->all();
            $weeksBefore = $weeks->fresh()->pluck('doc_path', 'id')->all();
            $this->metrics->events = [];
            $this->metrics->phase = 'word';
            $this->metrics->queries = 0;
            $this->metrics->query_ms = 0.0;
            $start = hrtime(true);
            app()->call([new GenerateLaporanBulananJob($report->fresh()), 'handle']);
            $wordSeconds = (hrtime(true) - $start) / 1e9;
            $report->refresh();
            $this->assertSame('completed', $report->doc_status, $report->doc_error ?? 'Word gagal.');
            $wordEvents = collect($this->metrics->events);
            $stages = [];
            foreach (['docx_pdf', 'pdf_images', 'prepare_image'] as $stage) {
                $events = $wordEvents->where('stage', $stage);
                $stages[$stage] = ['calls' => $events->count(), 'seconds' => round($events->sum('seconds'), 3)];
            }
            $cache = $wordEvents->where('stage', 'page_cache');
            $dailyGenerated = LaporanHarian::whereIn('id', $daily->modelKeys())->get()->filter(fn ($day) => $day->doc_path !== $dailyBefore[$day->id])->count();
            $weeksGenerated = $weeks->fresh()->filter(fn ($week) => $week->doc_path !== $weeksBefore[$week->id])->count();
            if ($scenario === 'warm') {
                $this->assertSame(0, $stages['docx_pdf']['calls']);
                $this->assertSame(0, $stages['pdf_images']['calls']);
                $this->assertSame(0, $dailyGenerated);
                $this->assertSame(0, $weeksGenerated);
            }
            if ($scenario === 'one_daily_changed') {
                $this->assertSame(1, $dailyGenerated);
                $this->assertGreaterThanOrEqual(1, $weeksGenerated);
            }
            $wordQueries = ['count' => $this->metrics->queries, 'seconds' => round($this->metrics->query_ms / 1000, 3)];
            $this->metrics->phase = 'release_pdf';
            $start = hrtime(true);
            app()->call([new ReleaseLaporanBulananPdfJob($report->fresh()), 'handle']);
            $pdfSeconds = (hrtime(true) - $start) / 1e9;
            $report->refresh();
            $this->assertSame('completed', $report->pdf_status, $report->pdf_error ?? 'PDF gagal.');
            $pdfPath = Storage::disk('local')->path($report->pdf_path);
            $info = new Process([config('services.poppler.pdfinfo'), $pdfPath]);
            $info->mustRun();
            $this->assertSame(1, preg_match('/^Pages:\s+(\d+)/m', $info->getOutput(), $matches));
            $baselinePages ??= (int) $matches[1];
            $this->assertSame($baselinePages, (int) $matches[1], 'Jumlah halaman harus stabil antar skenario.');
            fwrite(STDOUT, 'BENCHMARK_RESULT '.json_encode([
                'scenario' => $scenario, 'engine' => $this->metrics->engine,
                'word_seconds' => round($wordSeconds, 3), 'pdf_release_seconds' => round($pdfSeconds, 3),
                'total_seconds' => round($wordSeconds + $pdfSeconds, 3), 'word_stages' => $stages,
                'word_other_seconds' => round($wordSeconds - array_sum(array_column($stages, 'seconds')), 3),
                'word_queries' => $wordQueries,
                'rendered_pages' => $wordEvents->where('stage', 'pdf_images')->sum('pages'),
                'cache_hits' => $cache->where('hit', true)->count(), 'cache_misses' => $cache->where('hit', false)->count(),
                'daily_regenerated' => $dailyGenerated, 'weeks_regenerated' => $weeksGenerated,
                'final_pdf_pages' => (int) $matches[1],
                'word_bytes' => Storage::disk('local')->size($report->doc_path), 'pdf_bytes' => filesize($pdfPath),
            ], JSON_UNESCAPED_SLASHES).PHP_EOL);
        }
    }
}
