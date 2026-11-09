<?php

namespace Tests\Feature;

use App\Jobs\ProcessNcFoto;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFoto;
use App\Models\User;
use App\Services\LaporanNcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
    public function sync_foto_bukti_membuat_foto_1_1_dan_antrian(): void
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
        $this->assertSame('pending', $nc->foto->file_status);
        Queue::assertPushed(ProcessNcFoto::class, fn ($job) => $job->ncFoto->id === $nc->foto->id);
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
    public function foto_baru_menggantikan_foto_lama_beserta_filenya(): void
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

        $this->assertSame(1, LaporanNcFoto::count());
        $this->assertNull(LaporanNcFoto::find($lama->id));
        Storage::disk('local')->assertMissing('laporan-nc/foto/lama.webp');
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
