<?php

namespace Tests\Feature;

use App\Jobs\ProcessNcFile;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcFile;
use App\Models\User;
use App\Services\LaporanNcService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
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
    public function sync_menyimpan_nc_dengan_nomor_otomatis_dan_foto_ke_antrian(): void
    {
        Queue::fake();
        Storage::fake('local');

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

        // Bukti (dokumen form NC) diunggah via List NC → storeUploads
        $this->service->storeUploads($this->dataKapal->id, [$nc->id], [UploadedFile::fake()->create('form-nc.pdf', 64, 'application/pdf')], 'before', auth()->id());
        $this->assertTrue($nc->beforeFiles()->exists());
        Queue::assertPushed(ProcessNcFile::class);
    }

    #[Test]
    public function sync_update_nc_tersimpan_dan_hapus_yang_tidak_dikirim(): void
    {
        Queue::fake();
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'Satu'], ['row_key' => 'b', 'kendala' => 'Dua']]);

        $first = LaporanNc::orderBy('id')->first();
        $this->service->syncNc($laporan, [['row_key' => 'c'.$first->id, 'id' => $first->id, 'kendala' => 'Satu Edit']]);

        $this->assertSame(1, LaporanNc::count());
        $this->assertSame('Satu Edit', $first->fresh()->kendala);
    }

    #[Test]
    public function close_wajib_punya_file_after(): void
    {
        Queue::fake();
        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();

        $this->expectException(ValidationException::class);
        $this->service->toggleStatus($nc, auth()->id());
    }

    #[Test]
    public function close_dengan_file_after_dan_reopen_membersihkan_field(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();

        $this->service->storeUploads($this->dataKapal->id, [$nc->id], [UploadedFile::fake()->image('after.jpg')], 'after', auth()->id());
        $this->service->toggleStatus($nc->fresh(), auth()->id());

        $nc->refresh();
        $this->assertSame('closed', $nc->status->value);
        $this->assertNotNull($nc->closed_at);
        $this->assertSame(auth()->id(), $nc->closed_by);

        $this->service->toggleStatus($nc, auth()->id());
        $nc->refresh();
        $this->assertSame('open', $nc->status->value);
        $this->assertNull($nc->closed_at);
        $this->assertNull($nc->closed_by);
    }

    #[Test]
    public function satu_file_bisa_dilink_ke_beberapa_nc(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'NC1'], ['row_key' => 'b', 'kendala' => 'NC2']]);
        $ids = LaporanNc::pluck('id')->all();

        $this->service->storeUploads($this->dataKapal->id, $ids, [UploadedFile::fake()->image('shared.jpg')], 'before', auth()->id());

        $file = LaporanNcFile::firstOrFail();
        $this->assertSame(1, LaporanNcFile::count());
        $this->assertSame(2, $file->ncs()->count());
    }

    #[Test]
    public function rekap_mingguan_open_carry_over_dan_closed_hanya_satu_minggu_tambahan(): void
    {
        Queue::fake();

        $mulaiW = now()->startOfWeek();
        $selesaiW = $mulaiW->copy()->endOfWeek();
        $laporanW0 = $this->laporan($mulaiW->copy()->subWeek()->format('Y-m-d'));

        // Satu syncNc — sync bersifat full-sync (baris yang tidak dikirim dihapus)
        $this->service->syncNc($laporanW0, [
            ['row_key' => 'a', 'kendala' => 'Masih Open'],
            ['row_key' => 'b', 'kendala' => 'Closed minggu lalu'],
            ['row_key' => 'c', 'kendala' => 'Closed lama'],
        ]);
        [$open, $closedW1, $closedW2] = LaporanNc::orderBy('id')->get()->all();

        // NC open dibuat minggu lalu → tampil minggu ini & seterusnya
        $open->created_at = $mulaiW->copy()->subWeek();
        $open->save();

        // NC closed di W-1 → masih tampil di W
        $closedW1->created_at = $mulaiW->copy()->subWeek();
        $closedW1->update(['status' => 'closed', 'closed_at' => $mulaiW->copy()->subDay()]);

        // NC closed di W-2 → tidak tampil di W
        $closedW2->created_at = $mulaiW->copy()->subWeeks(3);
        $closedW2->update(['status' => 'closed', 'closed_at' => $mulaiW->copy()->subDays(9)]);

        $rekap = $this->service->rekapMingguan($this->dataKapal->id, $mulaiW, $selesaiW)->pluck('id');

        $this->assertTrue($rekap->contains($open->id));
        $this->assertTrue($rekap->contains($closedW1->id));
        $this->assertFalse($rekap->contains($closedW2->id));

        // Di W+1: closed W-1 sudah lewat window → hilang, open tetap tampil
        $rekapNext = $this->service->rekapMingguan($this->dataKapal->id, $mulaiW->copy()->addWeek(), $selesaiW->copy()->addWeek())->pluck('id');
        $this->assertTrue($rekapNext->contains($open->id));
        $this->assertFalse($rekapNext->contains($closedW1->id));
    }

    #[Test]
    public function delete_laporan_membersihkan_nc_dan_file_orphan(): void
    {
        Queue::fake();
        Storage::fake('local');

        $laporan = $this->laporan();
        $this->service->syncNc($laporan, [['row_key' => 'a', 'kendala' => 'X']]);
        $nc = LaporanNc::firstOrFail();
        $this->service->storeUploads($this->dataKapal->id, [$nc->id], [UploadedFile::fake()->image('f.jpg')], 'before', auth()->id());
        $file = LaporanNcFile::firstOrFail();

        $this->service->deleteForLaporan($laporan);
        $laporan->delete();

        $this->assertSame(0, LaporanNc::count());
        $this->assertSame(0, LaporanNcFile::count());
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
