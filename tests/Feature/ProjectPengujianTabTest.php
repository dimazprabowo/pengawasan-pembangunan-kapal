<?php

namespace Tests\Feature;

use App\Enums\PengujianCatalog;
use App\Livewire\MasterData\DataProyekManagement;
use App\Livewire\MasterData\ProjectPengujianManagement;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\Pengujian;
use App\Models\PengujianStatus;
use App\Models\User;
use App\Services\DataProyekService;
use App\Services\LaporanBulananWordService;
use App\Services\WordStyleService;
use Database\Seeders\DataKapalSeeder;
use Database\Seeders\DataProyekSeeder;
use Database\Seeders\GalanganSeeder;
use Database\Seeders\OwnerSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectPengujianTabTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            OwnerSeeder::class,
            GalanganSeeder::class,
            DataKapalSeeder::class,
            UserSeeder::class,
            DataProyekSeeder::class,
        ]);
    }

    #[Test]
    public function pengujian_tab_embeds_the_inline_register_component(): void
    {
        Livewire::actingAs(User::firstOrFail())
            ->test(DataProyekManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('setTab', 'pengujian')
            ->assertSeeLivewire('master-data.project-pengujian-management');
    }

    #[Test]
    public function rows_render_as_editable_inputs_and_mark_unsaved_until_saved_or_cancelled(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectPengujianManagement::class, ['dataKapal' => DataKapal::firstOrFail()]);
        $this->assertStringContainsString('wire:model.blur', $component->html());
        $this->assertStringContainsString('dirtyKeys[', $component->html());
        $this->assertStringContainsString('data-[unsaved=true]:bg-yellow-50', $component->html());
        $component->assertDontSee('data-unsaved="true"', false);
        $key = collect($component->get('drafts'))->keys()->first();
        $nama = $component->get("drafts.$key.nama_pengujian");
        $component->call('addRow')->assertSee('data-unsaved="true"', false);
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey)->assertDontSee('data-unsaved="true"', false)
            ->set("drafts.$key.nama_pengujian", $nama.' UI')->assertSet("drafts.$key.dirty", true)
            ->assertSee('data-draft-key="'.$key.'" data-unsaved="true"', false)
            ->call('saveRow', $key)->assertHasNoErrors()->assertDontSee('data-unsaved="true"', false);
        $this->assertSame($nama.' UI', Pengujian::findOrFail((int) substr($key, 4))->nama_pengujian);
    }

    #[Test]
    public function inline_create_cancel_and_delete_work_per_row(): void
    {
        $ship = DataKapal::firstOrFail();
        $statusId = PengujianStatus::firstOrFail()->id;
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectPengujianManagement::class, ['dataKapal' => $ship])
            ->call('addRow');
        $key = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->set("drafts.$key.nama_pengujian", '  Inline Pengujian  ')
            ->set("drafts.$key.pengujian_status_id", $statusId)
            ->set("drafts.$key.tanggal", '2026-03-01')
            ->set("drafts.$key.hasil", 'Lulus')
            ->call('sortBy', 'nama_pengujian')
            ->assertSet("drafts.$key.nama_pengujian", '  Inline Pengujian  ')
            ->call('saveRow', $key)->assertHasNoErrors();
        $pengujian = Pengujian::where('nama_pengujian', 'Inline Pengujian')->firstOrFail();
        $this->assertSame($statusId, $pengujian->pengujian_status_id);
        $this->assertSame($ship->id, $pengujian->data_kapal_id);
        $this->assertSame('2026-03-01', $pengujian->tanggal->toDateString());
        $component->call('addRow');
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey);
        $this->assertArrayNotHasKey($newKey, $component->get('drafts'));
        $component->call('confirmDelete', $pengujian->id)->assertSet('showDeleteModal', true)->call('delete');
        $this->assertNull(Pengujian::find($pengujian->id));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'pengujian', 'event' => 'deleted', 'subject_id' => $pengujian->id]);
    }

    #[Test]
    public function sorting_is_whitelisted_and_search_is_scoped_to_the_ship(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $base = ['pengujian_status_id' => PengujianStatus::firstOrFail()->id];
        $service->savePengujian($ship, $base + ['nama_pengujian' => 'Zebra Test']);
        $service->savePengujian($ship, $base + ['nama_pengujian' => 'Alpha Test']);
        $ascending = $service->getPengujians($ship, 'Test', 10, [], 'nama_pengujian', 'asc');
        $descending = $service->getPengujians($ship, 'Test', 10, [], 'nama_pengujian', 'desc');
        $invalid = $service->getPengujians($ship, 'Test', 10, [], 'nama_pengujian; DROP TABLE users');
        $this->assertSame('Alpha Test', $ascending->first()->nama_pengujian);
        $this->assertSame('Zebra Test', $descending->first()->nama_pengujian);
        $this->assertSame('Alpha Test', $invalid->first()->nama_pengujian);
        $this->assertSame(0, $service->getPengujians($ship, 'Tidak Ada Kata Kunci')->total());
        $otherShip = DataKapal::whereKeyNot($ship->id)->firstOrFail();
        $this->assertSame(Pengujian::where('data_kapal_id', $otherShip->id)->count(), $service->getPengujians($otherShip, '')->total());
        $this->assertSame(1, $service->getPengujians($ship, 'Inclining')->total());
        $statusFilter = Pengujian::where('nama_pengujian', 'NDT Test Block A & B')->firstOrFail()->pengujian_status_id;
        $this->assertSame(1, $service->getPengujians($ship, '', 10, ['status' => $statusFilter])->total());
    }

    #[Test]
    public function status_catalog_can_be_created_renamed_and_used_ones_are_protected(): void
    {
        $service = app(DataProyekService::class);
        $this->assertCount(3, $service->pengujianCatalogs(PengujianCatalog::Status));

        $status = $service->savePengujianCatalog(PengujianCatalog::Status, 'Gagal');
        $this->assertDatabaseHas('pengujian_statuses', ['id' => $status->id, 'nama' => 'Gagal']);
        $service->savePengujianCatalog(PengujianCatalog::Status, 'Gagal Total', $status->id);
        $this->assertSame('Gagal Total', $status->fresh()->nama);
        try {
            $service->savePengujianCatalog(PengujianCatalog::Status, 'gagal total');
            $this->fail('Duplicate catalog name should be rejected.');
        } catch (ValidationException) {
        }

        $usedId = Pengujian::firstOrFail()->pengujian_status_id;
        try {
            $service->deletePengujianCatalog(PengujianCatalog::Status, $usedId);
            $this->fail('Used catalog should not be deletable.');
        } catch (ValidationException) {
        }
        $service->deletePengujianCatalog(PengujianCatalog::Status, $status->id);
        $this->assertDatabaseMissing('pengujian_statuses', ['id' => $status->id]);

        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectPengujianManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('manageCatalog', 'status')->assertSet('showCatalog', true)
            ->set('catalogName', 'Katalog UI')->call('saveCatalog')->assertHasNoErrors();
        $this->assertDatabaseHas('pengujian_statuses', ['nama' => 'Katalog UI']);
        $component->call('confirmDelete', $usedId, 'status')->assertSet('showDeleteModal', true)->call('delete')
            ->assertSet('showDeleteModal', false);
        $this->assertDatabaseHas('pengujian_statuses', ['id' => $usedId]);
    }

    #[Test]
    public function monthly_word_lists_cumulative_pengujian_with_status_labels(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $cutoff = now()->copy()->addMonth();
        $service->savePengujian($ship, [
            'nama_pengujian' => 'Future Pengujian',
            'pengujian_status_id' => PengujianStatus::firstOrFail()->id,
            'tanggal' => $cutoff->copy()->addDay()->toDateString(),
        ]);
        $this->assertSame(3, $service->getPengujiansUpTo($ship, $cutoff)->count());
        $this->assertSame(4, $service->getPengujiansUpTo($ship, null)->count());

        $template = tempnam(sys_get_temp_dir(), 'pengujian-template-');
        $output = tempnam(sys_get_temp_dir(), 'pengujian-output-');
        try {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('${tabel_pengujian}');
            WordStyleService::prepare($word);
            IOFactory::createWriter($word, 'Word2007')->save($template);
            $processor = new TemplateProcessor($template);
            $report = new LaporanBulanan;
            $report->forceFill(['periode_selesai' => $cutoff]);
            $report->setRelation('dataKapal', $ship);
            (new \ReflectionMethod(LaporanBulananWordService::class, 'fillPengujian'))->invoke(app(LaporanBulananWordService::class), $processor, $report);
            $processor->saveAs($output);
            $zip = new \ZipArchive;
            $zip->open($output);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertStringContainsString('NDT Test Block A', $xml);
            $this->assertStringContainsString('Inclining Test', $xml);
            $this->assertStringContainsString('Selesai', $xml);
            $this->assertStringNotContainsString('Future Pengujian', $xml);
            $this->assertStringNotContainsString('${tabel_pengujian}', $xml);
        } finally {
            unlink($template);
            unlink($output);
        }
    }

    #[Test]
    public function unauthorized_users_cannot_open_and_view_only_cannot_write(): void
    {
        $ship = DataKapal::firstOrFail();
        Livewire::actingAs(User::factory()->create())
            ->test(ProjectPengujianManagement::class, ['dataKapal' => $ship])->assertForbidden();
        $viewer = User::factory()->create(['owner_id' => $ship->owner_id]);
        $viewer->givePermissionTo('proyek_data_view');
        Livewire::actingAs($viewer)->test(ProjectPengujianManagement::class, ['dataKapal' => $ship])
            ->call('addRow')->assertForbidden();
        Livewire::actingAs($viewer)->test(ProjectPengujianManagement::class, ['dataKapal' => $ship])
            ->call('confirmDelete', Pengujian::firstOrFail()->id)->assertForbidden();
    }

    #[Test]
    public function pengujian_cannot_be_read_or_written_to_another_ship(): void
    {
        $service = app(DataProyekService::class);
        $pengujian = Pengujian::firstOrFail();
        $other = DataKapal::whereKeyNot($pengujian->data_kapal_id)->firstOrFail();
        $this->expectException(ModelNotFoundException::class);
        $service->findPengujian($other, $pengujian->id);
    }
}
