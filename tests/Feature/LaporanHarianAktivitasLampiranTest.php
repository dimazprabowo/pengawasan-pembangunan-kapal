<?php

namespace Tests\Feature;

use App\Jobs\ProcessLaporanLampiran;
use App\Livewire\LaporanHarian\LaporanHarianCreate;
use App\Livewire\LaporanHarian\LaporanHarianEdit;
use App\Models\DataKapal;
use App\Models\KurvaSWorkGroup;
use App\Models\LaporanAktivitas;
use App\Models\LaporanHarian;
use App\Models\LaporanLampiran;
use App\Models\User;
use App\Services\LaporanHarianService;
use App\Services\LaporanHarianWordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LaporanHarianAktivitasLampiranTest extends TestCase
{
    use RefreshDatabase;

    private DataKapal $dataKapal;

    private KurvaSWorkGroup $wgLambung;

    private KurvaSWorkGroup $wgMesin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->dataKapal = DataKapal::create(['nama' => 'Kapal Uji Aktivitas', 'status' => 'active']);
        $this->wgLambung = KurvaSWorkGroup::create(['data_kapal_id' => $this->dataKapal->id, 'nama' => 'Konstruksi Lambung', 'bobot' => 60, 'sort_order' => 0]);
        $this->wgMesin = KurvaSWorkGroup::create(['data_kapal_id' => $this->dataKapal->id, 'nama' => 'Permesinan', 'bobot' => 40, 'sort_order' => 1]);
    }

    private function admin(): User
    {
        return User::firstOrFail();
    }

    private function laporan(): LaporanHarian
    {
        return LaporanHarian::create([
            'user_id' => $this->admin()->id,
            'data_kapal_id' => $this->dataKapal->id,
            'tanggal_laporan' => now()->subDays(2)->format('Y-m-d'),
        ]);
    }

    public function test_kategori_options_work_group_terurut_dan_lainnya_terakhir(): void
    {
        $options = app(LaporanHarianService::class)->kategoriOptions($this->dataKapal->id);

        $this->assertSame(['Konstruksi Lambung', 'Permesinan', LaporanHarianService::KATEGORI_LAINNYA], array_column($options, 'nama'));
        $this->assertNull(end($options)['work_group_id']);
    }

    public function test_create_menyimpan_aktivitas_per_kategori_dan_foto_ke_antrian(): void
    {
        Queue::fake();
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->dataKapal])
            ->call('addAktivitas', 0, $this->wgLambung->id)
            ->call('addAktivitas', 0, null);

        $rowKey = $component->get('items.0.aktivitas.0.row_key');

        $component
            ->set('items.0.aktivitas.0.aktivitas', 'Fit up blok lambung')
            ->set('items.0.aktivitas.1.aktivitas', 'Rapat koordinasi')
            ->set("lampiran.0.{$rowKey}.file", UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        $laporan = LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->sole();
        $aktivitas = $laporan->aktivitas()->get();

        $this->assertCount(2, $aktivitas);
        $this->assertSame($this->wgLambung->id, $aktivitas[0]->kurva_s_work_group_id);
        $this->assertSame('Konstruksi Lambung', $aktivitas[0]->kategori);
        $this->assertNull($aktivitas[1]->kurva_s_work_group_id);

        $lampiran = LaporanLampiran::sole();
        $this->assertSame($aktivitas[0]->id, $lampiran->laporan_aktivitas_id);
        $this->assertSame($laporan->id, $lampiran->laporan_harian_id);
        $this->assertSame('Fit up blok lambung', $lampiran->keterangan);
        Queue::assertPushed(ProcessLaporanLampiran::class, 1);
    }

    public function test_foto_tanpa_uraian_aktivitas_ditolak(): void
    {
        Storage::fake('local');

        $component = Livewire::actingAs($this->admin())
            ->test(LaporanHarianCreate::class, ['dataKapal' => $this->dataKapal])
            ->call('addAktivitas', 0, $this->wgMesin->id);

        $rowKey = $component->get('items.0.aktivitas.0.row_key');

        $component
            ->set("lampiran.0.{$rowKey}.file", UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasErrors(["lampiran.0.{$rowKey}.file"]);

        $this->assertFalse(LaporanHarian::where('data_kapal_id', $this->dataKapal->id)->exists());
    }

    public function test_work_group_milik_kapal_lain_dinormalisasi_ke_lainnya(): void
    {
        $laporan = $this->laporan();
        $wgLain = KurvaSWorkGroup::where('data_kapal_id', '!=', $this->dataKapal->id)->firstOrFail();

        app(LaporanHarianService::class)->syncAktivitas($laporan, [
            ['row_key' => 'n1', 'id' => null, 'work_group_id' => $wgLain->id, 'aktivitas' => 'Aktivitas X', 'pic' => ''],
        ]);

        $aktivitas = $laporan->aktivitas()->sole();
        $this->assertNull($aktivitas->kurva_s_work_group_id);
        $this->assertNull($aktivitas->kategori);
    }

    public function test_edit_update_aktivitas_tetap_id_dan_hapus_aktivitas_beserta_foto(): void
    {
        $laporan = $this->laporan();
        $tetap = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgLambung->id, 'kategori' => 'Konstruksi Lambung', 'aktivitas' => 'Awal']);
        $dihapus = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Dengan foto']);
        $dihapus->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_name' => 'a.webp', 'file_status' => 'completed']);

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $this->dataKapal, 'laporanHarian' => $laporan])
            ->set('aktivitas.0.aktivitas', 'Diubah')
            ->call('confirmRemoveAktivitas', 1)
            ->assertSet('deletingAktivitasHasFoto', true)
            ->call('removeAktivitasConfirmed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Diubah', $tetap->fresh()->aktivitas);
        $this->assertNull(LaporanAktivitas::find($dihapus->id));
        $this->assertSame(0, LaporanLampiran::where('laporan_aktivitas_id', $dihapus->id)->count());
    }

    public function test_edit_uraian_aktivitas_berfoto_tidak_boleh_dikosongkan(): void
    {
        $laporan = $this->laporan();
        $aktivitas = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgLambung->id, 'kategori' => 'Konstruksi Lambung', 'aktivitas' => 'Ada foto']);
        $aktivitas->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_name' => 'a.webp', 'file_status' => 'completed']);

        Livewire::actingAs($this->admin())
            ->test(LaporanHarianEdit::class, ['dataKapal' => $this->dataKapal, 'laporanHarian' => $laporan])
            ->set('aktivitas.0.aktivitas', '')
            ->call('save')
            ->assertHasErrors(['aktivitas.0.aktivitas']);
    }

    public function test_aktivitas_per_kategori_hanya_kategori_berisi_urut_sort_order(): void
    {
        $laporan = $this->laporan();
        $laporan->aktivitas()->create(['aktivitas' => 'Umum']);
        $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Mesin']);

        $groups = app(LaporanHarianService::class)->aktivitasPerKategori($laporan->fresh());

        $this->assertSame(['Permesinan', LaporanHarianService::KATEGORI_LAINNYA], $groups->pluck('nama')->all());
    }

    public function test_generate_word_tidak_menyertakan_kategori_kosong_dan_grid_lampiran(): void
    {
        $laporan = $this->laporan();
        $aktivitas = $laporan->aktivitas()->create(['kurva_s_work_group_id' => $this->wgMesin->id, 'kategori' => 'Permesinan', 'aktivitas' => 'Overhaul mesin induk']);

        $path = 'laporan-lampiran/harian/test_'.uniqid().'.png';
        $img = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($img);
        Storage::disk('local')->put($path, ob_get_clean());
        $aktivitas->lampiran()->create(['laporan_harian_id' => $laporan->id, 'file_path' => $path, 'file_name' => 'foto.png', 'file_status' => 'completed']);

        $docPath = app(LaporanHarianWordService::class)->generate($laporan->fresh());

        try {
            $zip = new \ZipArchive;
            $zip->open(storage_path('app/'.$docPath));
            $text = strip_tags($zip->getFromName('word/document.xml'));
            $zip->close();

            $this->assertStringContainsString('Permesinan', $text);
            $this->assertStringNotContainsString('Konstruksi Lambung', $text);
            $this->assertStringContainsString('Overhaul mesin induk', $text);
            $this->assertStringNotContainsString('${', $text);
        } finally {
            @unlink(storage_path('app/'.$docPath));
            Storage::disk('local')->delete($path);
        }
    }
}
