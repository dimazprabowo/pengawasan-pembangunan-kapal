<?php

namespace Tests\Feature;

use App\Enums\NcFileKategori;
use App\Jobs\ProcessNcFoto;
use App\Livewire\LaporanHarian\LaporanNcList;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFoto;
use App\Models\User;
use App\Services\LaporanNcService;
use App\Services\QueueStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LaporanNcTest extends TestCase
{
    use RefreshDatabase;

    private DataKapal $dataKapal;

    private LaporanNcService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dataKapal = DataKapal::create(['nama' => 'Kapal Uji NC', 'status' => 'active']);
        $this->service = app(LaporanNcService::class);
        $this->actingAs(User::firstOrFail());
    }

    private function laporan(?string $tanggal = null): LaporanHarian
    {
        return LaporanHarian::create([
            'user_id' => auth()->id(),
            'data_kapal_id' => $this->dataKapal->id,
            'tanggal_laporan' => $tanggal ?? now()->subDays(2)->format('Y-m-d'),
        ]);
    }

    #[Test]
    public function sync_menyimpan_nc_dengan_nomor_otomatis(): void
    {
        $laporan = $this->laporan();
        $row = $this->service->newNcRow();
        $row['kendala'] = 'Las tidak sesuai WPS';
        $row['tindakan'] = 'Perbaikan ulang';
        $row['pic'] = 'Welder A';

        $this->service->syncNc($laporan, [$row]);

        $nc = LaporanNc::firstOrFail();
        $this->assertSame('NC-001', $nc->nomor);
        $this->assertSame($this->dataKapal->id, $nc->data_kapal_id);
        $this->assertSame('open', $nc->status->value);
    }

    #[Test]
    public function sync_update_nc_tersimpan_dan_hapus_yang_tidak_dikirim(): void
    {
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'Satu'], ['row_key' => 'b', 'kendala' => 'Dua']]);

        $first = LaporanNc::orderBy('id')->first();
        $this->service->syncNc($laporan, [['row_key' => 'c'.$first->id, 'id' => $first->id, 'kendala' => 'Satu Edit']]);

        $this->assertSame(1, LaporanNc::count());
        $this->assertSame('Satu Edit', $first->fresh()->kendala);
    }

    #[Test]
    public function sync_file_bukti_membuat_lampiran_dan_antrian(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $row = $this->service->newNcRow();
        $row['kendala'] = 'Las tidak sesuai WPS';

        $count = $this->service->syncNc($laporan, [$row], [
            $row['row_key'] => ['file' => UploadedFile::fake()->image('bukti.jpg'), 'cropData' => null],
        ]);

        $nc = LaporanNc::firstOrFail();
        $this->assertSame(1, $count);
        $this->assertSame('pending', $nc->foto->first()->file_status);
        Queue::assertPushed(ProcessNcFoto::class, fn ($job) => $job->ncFoto->id === $nc->foto->first()->id);
    }

    #[Test]
    public function baris_nc_hanya_berisi_foto_tetap_tersimpan(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $row = $this->service->newNcRow();

        $this->service->syncNc($laporan, [$row], [
            $row['row_key'] => ['file' => UploadedFile::fake()->image('bukti.jpg'), 'cropData' => null],
        ]);

        $nc = LaporanNc::firstOrFail();
        $this->assertSame('-', $nc->kendala);
        $this->assertNotNull($nc->foto);
    }

    #[Test]
    public function file_baru_ditambahkan_tanpa_menghapus_file_lama(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();

        Storage::disk('local')->put('laporan-nc/foto/lama.webp', 'x');
        $lama = $nc->foto()->create(['file_name' => 'lama.webp', 'file_path' => 'laporan-nc/foto/lama.webp', 'file_status' => 'completed']);

        $this->service->syncNc($laporan, [['row_key' => 'c'.$nc->id, 'id' => $nc->id, 'kendala' => 'X']], [
            'c'.$nc->id => ['file' => UploadedFile::fake()->image('baru.jpg'), 'cropData' => null],
        ]);

        $this->assertSame(2, LaporanNcFoto::count());
        $this->assertNotNull(LaporanNcFoto::find($lama->id));
        Storage::disk('local')->assertExists('laporan-nc/foto/lama.webp');
    }

    #[Test]
    public function reopen_membersihkan_field_closed(): void
    {
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();
        $nc->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);

        $this->service->reopen($nc, auth()->id(), 'Salah pilih NC saat generate penutupan');

        $nc->refresh();
        $this->assertSame('open', $nc->status->value);
        $this->assertNull($nc->closed_at);
        $this->assertNull($nc->closed_by);
        $this->assertNotNull($nc->reopened_at);
        $this->assertSame(auth()->id(), $nc->reopened_by);
        $this->assertSame('Salah pilih NC saat generate penutupan', $nc->reopen_note);
    }

    #[Test]
    public function rekap_mingguan_open_carry_over_dan_closed_hanya_tampil_di_minggu_closed(): void
    {
        $mulaiW = now()->startOfWeek();
        $selesaiW = $mulaiW->copy()->endOfWeek();
        $laporanW0 = $this->laporan($mulaiW->copy()->subWeek()->format('Y-m-d'));

        // Satu syncNc — sync bersifat full-sync (baris yang tidak dikirim dihapus)
        $this->service->syncNc($laporanW0, [
            ['row_key' => 'a', 'kendala' => 'Masih Open'],
            ['row_key' => 'b', 'kendala' => 'Closed minggu ini'],
            ['row_key' => 'c', 'kendala' => 'Closed minggu lalu'],
        ]);
        [$open, $closedW, $closedPrev] = LaporanNc::orderBy('id')->get()->all();

        $open->created_at = $mulaiW->copy()->subWeek();
        $open->save();

        $closedW->created_at = $mulaiW->copy()->subWeek();
        $closedW->update(['status' => 'closed', 'closed_at' => $mulaiW->copy()->addDay()]);

        $closedPrev->created_at = $mulaiW->copy()->subWeeks(2);
        $closedPrev->update(['status' => 'closed', 'closed_at' => $mulaiW->copy()->subDay()]);

        $rekap = $this->service->rekapMingguan($this->dataKapal->id, $mulaiW, $selesaiW)->pluck('id');

        $this->assertTrue($rekap->contains($open->id));
        $this->assertTrue($rekap->contains($closedW->id));
        $this->assertFalse($rekap->contains($closedPrev->id));

        $rekapNext = $this->service->rekapMingguan($this->dataKapal->id, $mulaiW->copy()->addWeek(), $selesaiW->copy()->addWeek())->pluck('id');
        $this->assertTrue($rekapNext->contains($open->id));
        $this->assertFalse($rekapNext->contains($closedW->id));
    }

    #[Test]
    public function delete_laporan_membersihkan_nc_dan_foto(): void
    {
        Storage::fake('local');

        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();
        Storage::disk('local')->put('laporan-nc/foto/f.webp', 'x');
        $nc->foto()->create(['file_name' => 'f.webp', 'file_path' => 'laporan-nc/foto/f.webp', 'file_status' => 'completed']);

        $this->service->deleteForLaporan($laporan);
        $laporan->delete();

        $this->assertSame(0, LaporanNc::count());
        $this->assertSame(0, LaporanNcFoto::count());
        Storage::disk('local')->assertMissing('laporan-nc/foto/f.webp');
    }

    #[Test]
    public function multi_file_open_dari_form_dan_close_tidak_mengubah_status_nc(): void
    {
        Queue::fake();
        Storage::fake('local');
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'Temuan']], [
            'a_satu' => ['file' => UploadedFile::fake()->image('satu.jpg')],
            'a_dua' => ['file' => UploadedFile::fake()->image('dua.png')],
        ]);
        $nc = LaporanNc::firstOrFail();
        $this->assertCount(2, $nc->foto);
        $this->assertSame(['open'], $nc->foto->pluck('kategori')->map->value->unique()->values()->all());
        $nc->update(['status' => 'closed', 'closed_at' => now()]);
        $this->service->addFiles($nc, [['file' => UploadedFile::fake()->image('close.jpg')]], NcFileKategori::Close);
        $this->assertSame('closed', $nc->fresh()->status->value);
        $this->assertCount(3, $nc->fresh()->foto);
        Queue::assertPushed(ProcessNcFoto::class, 3);
    }

    #[Test]
    public function file_close_dari_form_tersimpan_dengan_marker_key(): void
    {
        Queue::fake();
        Storage::fake('local');
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'Temuan']], [
            'a_satu' => ['file' => UploadedFile::fake()->image('open.jpg')],
            'a_close_dua' => ['file' => UploadedFile::fake()->image('close.jpg')],
        ]);
        $nc = LaporanNc::firstOrFail();
        $this->assertCount(2, $nc->foto);
        $this->assertSame(
            ['close', 'open'],
            $nc->foto->pluck('kategori')->map->value->sort()->values()->all()
        );
        $this->assertTrue($nc->fresh()->isOpen());
        Queue::assertPushed(ProcessNcFoto::class, 2);
    }

    #[Test]
    public function list_nc_upload_open_close_dan_hapus_individual(): void
    {
        Queue::fake();
        Storage::fake('local');
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'Temuan']]);
        $nc = LaporanNc::firstOrFail();
        $component = Livewire::test(LaporanNcList::class, ['dataKapal' => $this->dataKapal])
            ->set('ncUploads.'.$nc->id.'.open.satu.file', UploadedFile::fake()->image('open.jpg'))
            ->call('uploadNcFiles', $nc->id, 'open')
            ->assertHasNoErrors()
            ->set('ncUploads.'.$nc->id.'.close.dua.file', UploadedFile::fake()->image('close.jpg'))
            ->call('uploadNcFiles', $nc->id, 'close')
            ->assertHasNoErrors();
        $this->assertCount(2, $nc->fresh()->foto);
        $this->assertTrue($nc->fresh()->isOpen());
        $file = $nc->fresh()->foto->first();
        $component->call('confirmDeleteFile', $file->id)->assertSet('showDeleteFileModal', true)->call('deleteNcFile');
        $this->assertCount(1, $nc->fresh()->foto);
    }

    #[Test]
    public function file_nc_lintas_kapal_tidak_dapat_dihapus_dari_list(): void
    {
        $this->service->syncNc($this->laporan(), [['row_key' => 'a', 'kendala' => 'Temuan']]);
        $nc = LaporanNc::firstOrFail();
        $file = $nc->foto()->create(['file_name' => 'satu.pdf', 'file_status' => 'pending']);
        $other = DataKapal::create(['nama' => 'Kapal Lain', 'status' => 'active']);
        Livewire::test(LaporanNcList::class, ['dataKapal' => $other])
            ->call('confirmDeleteFile', $file->id)->assertNotFound();
        $this->assertNotNull($file->fresh());
    }

    #[Test]
    public function delete_file_nc_membersihkan_halaman_pdf(): void
    {
        Storage::fake('local');
        $this->service->syncNc($this->laporan(), [['row_key' => 'a', 'kendala' => 'Temuan']]);
        $nc = LaporanNc::firstOrFail();
        Storage::disk('local')->put('nc/source.pdf', 'pdf');
        Storage::disk('local')->put('nc/page.webp', 'page');
        $file = $nc->foto()->create(['file_name' => 'source.pdf', 'file_path' => 'nc/source.pdf', 'file_status' => 'completed', 'rendered_pages' => ['nc/page.webp']]);
        $this->service->deleteFoto($file);
        Storage::disk('local')->assertMissing(['nc/source.pdf', 'nc/page.webp']);
        $this->assertNotNull($nc->fresh());
    }

    #[Test]
    public function upload_file_nc_ditolak_tanpa_permission_update(): void
    {
        Queue::fake();
        Storage::fake('local');
        $this->service->syncNc($this->laporan(), [['row_key' => 'a', 'kendala' => 'Temuan']]);
        $nc = LaporanNc::firstOrFail();
        $user = User::create(['name' => 'Pembaca NC', 'email' => 'reader-nc@x.test', 'password' => bcrypt('x')]);
        $user->givePermissionTo('laporan_view');
        $this->actingAs($user);
        Livewire::test(LaporanNcList::class, ['dataKapal' => $this->dataKapal])
            ->set('ncUploads.'.$nc->id.'.open.satu.file', UploadedFile::fake()->image('open.jpg'))
            ->call('uploadNcFiles', $nc->id, 'open');
        $this->assertCount(0, $nc->fresh()->foto);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function job_pdf_menyimpan_asli_halaman_dan_status_completed(): void
    {
        if (! is_executable(config('services.poppler.pdftoppm')) || ! is_executable(config('services.poppler.pdfinfo'))) {
            $this->markTestSkipped('Poppler belum tersedia pada path konfigurasi.');
        }
        Storage::fake('local');
        $this->service->syncNc($this->laporan(), [['row_key' => 'a', 'kendala' => 'Temuan']]);
        $nc = LaporanNc::firstOrFail();
        $file = $nc->foto()->create(['kategori' => 'close', 'file_name' => 'scan.pdf', 'file_status' => 'pending']);
        Storage::disk('local')->put('temp/nc.pdf', Pdf::loadHTML('<p>Scan NC</p>')->output());
        (new ProcessNcFoto($file, 'temp/nc.pdf'))->handle(app(QueueStatusService::class));
        $file->refresh();
        $this->assertTrue($file->isFileCompleted());
        $this->assertTrue($file->isPdf());
        $this->assertCount(1, $file->rendered_pages);
        Storage::disk('local')->assertExists(array_merge([$file->file_path], $file->rendered_pages));
        Storage::disk('local')->assertMissing('temp/nc.pdf');
        $this->assertTrue($nc->fresh()->isOpen());
    }

    #[Test]
    public function permission_laporan_nc_update_mengontrol_policy(): void
    {
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();

        $this->assertTrue(auth()->user()->can('update', $nc));

        $tanpaIzin = User::create(['name' => 'No NC', 'email' => 'no-nc@x.test', 'password' => bcrypt('x')]);
        $this->assertFalse($tanpaIzin->can('update', $nc));
        $this->assertFalse($tanpaIzin->can('viewAny', LaporanNc::class));
    }
}
