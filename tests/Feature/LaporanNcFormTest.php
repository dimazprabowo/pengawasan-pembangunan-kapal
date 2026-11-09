<?php

namespace Tests\Feature;

use App\Enums\NcFormJenis;
use App\Enums\NcFormStatus;
use App\Jobs\ProcessNcFormFile;
use App\Livewire\LaporanHarian\LaporanNcList;
use App\Models\DataKapal;
use App\Models\LaporanHarian;
use App\Models\LaporanNc;
use App\Models\LaporanNcForm;
use App\Models\User;
use App\Services\LaporanNcFormService;
use App\Services\LaporanNcService;
use App\Services\LaporanNcWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alur Form NC: Form Temuan per laporan harian, Form Penutupan lintas hari,
 * upload scan TTD penutupan → semua NC di dalamnya Closed.
 */
class LaporanNcFormTest extends TestCase
{
    use RefreshDatabase;

    private DataKapal $dataKapal;

    private LaporanNcFormService $forms;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dataKapal = DataKapal::create(['nama' => 'Kapal Uji Form NC', 'status' => 'active']);
        $this->forms = app(LaporanNcFormService::class);
        $this->actingAs(User::firstOrFail());
    }

    private function laporanDenganNc(string $tanggal, array $kendala): LaporanHarian
    {
        $laporan = LaporanHarian::create([
            'user_id' => auth()->id(),
            'data_kapal_id' => $this->dataKapal->id,
            'tanggal_laporan' => $tanggal,
        ]);
        app(LaporanNcService::class)->syncNc($laporan, array_map(
            fn ($k, $i) => ['row_key' => "r{$i}", 'kendala' => $k, 'pic' => 'PIC '.$i],
            $kendala, array_keys($kendala)
        ));

        return $laporan;
    }

    #[Test]
    public function form_temuan_berisi_nc_hari_itu_dan_generate_ulang_menyegarkan_draft(): void
    {
        $laporan = $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['A', 'B']);

        $form = $this->forms->createTemuan($laporan);
        $this->assertSame($this->dataKapal->kode.'-NCR-001', $form->nomor);
        $this->assertSame(NcFormJenis::Temuan, $form->jenis);
        $this->assertSame(2, $form->ncs()->count());
        $this->assertSame(NcFormStatus::MenungguTtd, $form->status());

        // NC baru di hari yang sama → draft yang sama disegarkan, nomor tetap
        LaporanNc::create(['laporan_harian_id' => $laporan->id, 'data_kapal_id' => $this->dataKapal->id, 'kendala' => 'C']);
        $ulang = $this->forms->createTemuan($laporan);

        $this->assertSame($form->id, $ulang->id);
        $this->assertSame(3, $ulang->ncs()->count());
        $this->assertSame(1, LaporanNcForm::count());
    }

    #[Test]
    public function form_temuan_baru_hanya_untuk_nc_yang_belum_masuk_form_bertanda_tangan(): void
    {
        Queue::fake();
        Storage::fake('local');
        $laporan = $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['A']);

        $form = $this->forms->createTemuan($laporan);
        $this->forms->uploadSigned($form, UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'), auth()->id());

        $this->expectException(ValidationException::class);
        $this->forms->createTemuan($laporan);
    }

    #[Test]
    public function upload_ttd_form_penutupan_menutup_semua_nc_lintas_hari(): void
    {
        Queue::fake();
        Storage::fake('local');
        $hari1 = $this->laporanDenganNc(now()->subDays(4)->toDateString(), ['A']);
        $hari2 = $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['B', 'C']);
        $ids = LaporanNc::pluck('id')->all();

        [$form, $skipped] = $this->forms->createPenutupan($this->dataKapal->id, $ids);
        $this->assertSame(0, $skipped);
        $this->assertSame(3, $form->ncs()->count());
        $this->assertSame(3, LaporanNc::where('status', 'open')->count(), 'Draft belum menutup NC');

        $closed = $this->forms->uploadSigned($form, UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'), auth()->id());

        $this->assertSame(3, $closed);
        $this->assertSame(0, LaporanNc::where('status', 'open')->count());
        $this->assertNotNull(LaporanNc::first()->closed_at);
        $this->assertFalse($form->fresh()->isDraft());
        $this->assertSame('pending', $form->fresh()->file_status);
        Queue::assertPushed(ProcessNcFormFile::class);
        $this->assertNotNull($hari1->fresh());
        $this->assertNotNull($hari2->fresh());
    }

    #[Test]
    public function nc_di_draft_penutupan_lain_dilewati_dan_hapus_draft_membebaskannya(): void
    {
        $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['A', 'B']);
        [$a, $b] = LaporanNc::orderBy('id')->get()->all();

        [$draft] = $this->forms->createPenutupan($this->dataKapal->id, [$a->id]);
        [$form2, $skipped] = $this->forms->createPenutupan($this->dataKapal->id, [$a->id, $b->id]);

        $this->assertSame(1, $skipped);
        $this->assertSame([$b->id], $form2->ncs()->pluck('laporan_nc.id')->all());

        $this->forms->deleteDraft($draft);
        [$form3] = $this->forms->createPenutupan($this->dataKapal->id, [$a->id]);
        $this->assertSame([$a->id], $form3->ncs()->pluck('laporan_nc.id')->all());
    }

    #[Test]
    public function form_bertanda_tangan_tidak_bisa_dihapus(): void
    {
        Queue::fake();
        Storage::fake('local');
        $laporan = $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['A']);
        $form = $this->forms->createTemuan($laporan);
        $this->forms->uploadSigned($form, UploadedFile::fake()->create('ttd.pdf', 50, 'application/pdf'), auth()->id());

        // User biasa ber-izin kelola NC (super admin mem-bypass Policy via Gate::before)
        $pengelola = User::create(['name' => 'Pengelola NC', 'email' => 'pengelola-nc@x.test', 'password' => bcrypt('x')]);
        $pengelola->givePermissionTo('laporan_nc_update');
        $this->assertTrue($pengelola->can('delete', LaporanNcForm::create([
            'data_kapal_id' => $this->dataKapal->id, 'jenis' => 'penutupan', 'nomor' => $this->dataKapal->kode.'-NCR-099', 'tanggal' => now()->toDateString(),
        ])));
        $this->assertFalse($pengelola->can('delete', $form->fresh()));

        $this->expectException(ValidationException::class);
        $this->forms->deleteDraft($form->fresh());
    }

    #[Test]
    public function dokumen_word_terisi_placeholder_dari_nc(): void
    {
        $laporan = $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['Las porositas', 'Spasi frame']);
        $form = $this->forms->createTemuan($laporan);

        $path = app(LaporanNcWordService::class)->generate($form);
        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString($this->dataKapal->kode.'-NCR-001', $xml);
        $this->assertStringContainsString('NC-001', $xml, 'Kolom No tabel diisi nomor NC');
        $this->assertStringContainsString('Las porositas', $xml);
        $this->assertStringContainsString('Spasi frame', $xml);
        $this->assertStringNotContainsString('${', $xml, 'Semua placeholder harus tergantikan');
        $this->assertStringNotContainsString('Lampiran', $xml, 'Blok lampiran dihapus bila tanpa foto');
    }

    #[Test]
    public function livewire_list_nc_generate_penutupan_dari_checklist(): void
    {
        $this->laporanDenganNc(now()->subDays(3)->toDateString(), ['A']);
        $nc = LaporanNc::firstOrFail();

        Livewire::test(LaporanNcList::class, ['dataKapal' => $this->dataKapal])
            ->call('toggleNcSelect', $nc->id)
            ->call('generatePenutupan')
            ->assertSet('selectedNcIds', []);

        $this->assertSame(1, LaporanNcForm::where('jenis', 'penutupan')->count());
    }

    #[Test]
    public function user_tanpa_izin_tidak_bisa_membuat_form(): void
    {
        $tanpaIzin = User::create(['name' => 'No NC', 'email' => 'no-ncform@x.test', 'password' => bcrypt('x')]);

        $this->assertFalse($tanpaIzin->can('create', LaporanNcForm::class));
        $this->assertTrue(auth()->user()->can('create', LaporanNcForm::class));
    }
}
