<?php

namespace Tests\Feature;

use App\Jobs\ProcessLaporanExternal;
use App\Jobs\ProcessLaporanLampiran;
use App\Jobs\ProcessNcFormFile;
use App\Jobs\ProcessNcFoto;
use App\Jobs\ProcessRapatFile;
use App\Models\DataKapal;
use App\Models\LaporanAktivitas;
use App\Models\LaporanExternal;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\LaporanNcFoto;
use App\Models\Rapat;
use App\Models\RapatFile;
use App\Models\RapatJenis;
use App\Models\User;
use App\Services\FileStorageService;
use App\Services\PdfPageService;
use App\Services\QueueStatusService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class FileProcessingJobsTest extends TestCase
{
    use RefreshDatabase;

    public static function kinds(): array
    {
        return [['lampiran'], ['nc'], ['form'], ['external'], ['external-pdf'], ['rapat']];
    }

    private function fixture(string $kind): array
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $kapal = DataKapal::create(['nama' => 'Kapal Test File', 'status' => 'active']);
        $harian = LaporanHarian::create(['user_id' => $user->id, 'data_kapal_id' => $kapal->id, 'tanggal_laporan' => today()]);
        $attributes = ['file_name' => 'foto.php', 'file_path' => 'old/file.webp', 'file_status' => 'pending'];
        $model = match ($kind) {
            'lampiran' => LaporanLampiran::create($attributes + [
                'laporan_harian_id' => $harian->id,
                'laporan_aktivitas_id' => LaporanAktivitas::create([
                    'laporan_harian_id' => $harian->id, 'aktivitas' => 'Uji lampiran',
                ])->id,
            ]),
            'nc' => LaporanNcFoto::create($attributes + ['laporan_nc_id' => LaporanNc::create([
                'laporan_harian_id' => $harian->id, 'data_kapal_id' => $kapal->id, 'kendala' => 'Uji file',
            ])->id, 'rendered_pages' => ['old/page.webp']]),
            'form' => LaporanNcForm::create($attributes + [
                'data_kapal_id' => $kapal->id, 'jenis' => 'penutupan', 'nomor' => 'QA-NCR-001', 'tanggal' => today(),
            ]),
            'rapat' => RapatFile::create($attributes + ['rapat_id' => Rapat::create([
                'data_kapal_id' => $kapal->id, 'tanggal' => today(),
                'rapat_jenis_id' => RapatJenis::firstOrCreate(['nama' => 'Mingguan'])->id,
            ])->id]),
            default => LaporanExternal::create($attributes + ['judul' => 'Uji File', 'rendered_pages' => ['old/page.webp']]),
        };
        Storage::disk('local')->put('old/file.webp', 'previous file');
        if (in_array($kind, ['nc', 'external', 'external-pdf'])) {
            Storage::disk('local')->put('old/page.webp', 'previous page');
        }
        $upload = UploadedFile::fake()->image('foto.png', 40, 20);
        if ($kind === 'external-pdf') {
            $upload = UploadedFile::fake()->createWithContent('scan.pdf', "%PDF-1.4\nscan");
            $this->mock(PdfPageService::class)->shouldReceive('render')->andReturnUsing(function () {
                $page = 'laporan-external/pages/'.Str::uuid().'/page-1.webp';
                Storage::disk('local')->put($page, 'rendered page');

                return [$page];
            });
        }
        $temp = app(FileStorageService::class)->storeTemp($upload, 'test-files');
        $content = Storage::disk('local')->get($temp);
        $crop = ['x' => 0, 'y' => 0, 'width' => 12, 'height' => 10];
        $job = match ($kind) {
            'lampiran' => new ProcessLaporanLampiran($model, $temp, $crop),
            'nc' => new ProcessNcFoto($model, $temp, $crop),
            'rapat' => new ProcessRapatFile($model, $temp, $crop),
            'form' => new ProcessNcFormFile($model, $temp, 'old/file.webp'),
            default => new ProcessLaporanExternal($model->id, $temp),
        };

        return [$model, $job, $temp, $content];
    }

    #[Test]
    #[DataProvider('kinds')]
    public function completed_upload_cleans_temp_and_old_files_without_changing_signed_bytes(string $kind): void
    {
        [$model, $job, $temp, $original] = $this->fixture($kind);
        $job->handle(app(QueueStatusService::class));
        $model->refresh();

        $this->assertSame('completed', $model->file_status);
        Storage::disk('local')->assertExists($model->file_path);
        Storage::disk('local')->assertMissing([$temp, 'old/file.webp']);
        $extension = match ($kind) {
            'form' => 'png', 'external-pdf' => 'pdf', default => 'webp'
        };
        $this->assertStringEndsWith('.'.$extension, $model->file_path);
        $this->assertStringEndsWith('.'.$extension, $model->file_name);
        if ($kind === 'form' || $kind === 'external-pdf') {
            $this->assertSame($original, Storage::disk('local')->get($model->file_path));
        } else {
            $dimensions = getimagesize(Storage::disk('local')->path($model->file_path));
            $this->assertSame(IMAGETYPE_WEBP, $dimensions[2]);
            if ($kind !== 'external') {
                $this->assertSame([12, 10], array_slice($dimensions, 0, 2));
            }
        }
        $path = $model->file_path;
        $job->handle(app(QueueStatusService::class));
        $this->assertSame($path, $model->fresh()->file_path);
        $job->failed(new RuntimeException('Late cleanup failure'));
        $this->assertSame('completed', $model->fresh()->file_status);
    }

    #[Test]
    #[DataProvider('kinds')]
    public function failed_metadata_save_rolls_back_keeps_originals_and_can_retry(string $kind): void
    {
        [$model, $job, $temp] = $this->fixture($kind);
        $reject = true;
        $model::updated(function (Model $record) use (&$reject) {
            if ($reject && $record->file_status === 'completed') {
                throw new RuntimeException('Simulated metadata failure');
            }
        });

        try {
            $job->handle(app(QueueStatusService::class));
            $this->fail('Metadata failure must be propagated to the queue.');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated metadata failure', $e->getMessage());
        }

        $this->assertSame('failed', $model->fresh()->file_status);
        $this->assertSame('old/file.webp', $model->fresh()->file_path);
        Storage::disk('local')->assertExists([$temp, 'old/file.webp']);
        $this->assertCount(in_array($kind, ['lampiran', 'form', 'rapat']) ? 2 : 3, Storage::disk('local')->allFiles());

        $reject = false;
        $job->handle(app(QueueStatusService::class));
        $this->assertSame('completed', $model->fresh()->file_status);
        Storage::disk('local')->assertMissing([$temp, 'old/file.webp']);
    }

    #[Test]
    public function exhausted_retries_remove_temp_without_deleting_previous_file(): void
    {
        [$model, $job, $temp] = $this->fixture('external');
        $job->failed(new RuntimeException('Internal failure'));

        $this->assertSame('failed', $model->fresh()->file_status);
        $this->assertStringNotContainsString('Internal failure', $model->fresh()->file_error);
        Storage::disk('local')->assertMissing($temp);
        Storage::disk('local')->assertExists('old/file.webp');
    }
}
