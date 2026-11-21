<?php

namespace Tests\Feature;

use App\Enums\VendorCatalog;
use App\Livewire\MasterData\DataProyekManagement;
use App\Livewire\MasterData\ProjectVendorManagement;
use App\Models\DataKapal;
use App\Models\KurvaSRencana;
use App\Models\LaporanBulanan;
use App\Models\ProjectVendor;
use App\Models\User;
use App\Models\VendorLingkup;
use App\Services\DataProyekService;
use App\Services\LaporanBulananWordService;
use App\Services\WordStyleService;
use Database\Seeders\DataKapalSeeder;
use Database\Seeders\DataProyekSeeder;
use Database\Seeders\GalanganSeeder;
use Database\Seeders\KurvaSWorkGroupSeeder;
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

class ProjectVendorTabTest extends TestCase
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
            KurvaSWorkGroupSeeder::class,
        ]);
    }

    #[Test]
    public function vendor_tab_embeds_the_inline_register_component(): void
    {
        Livewire::actingAs(User::firstOrFail())
            ->test(DataProyekManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('setTab', 'vendor')
            ->assertSeeLivewire('master-data.project-vendor-management');
    }

    #[Test]
    public function rows_render_as_editable_inputs_and_mark_unsaved_until_saved_or_cancelled(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectVendorManagement::class, ['dataKapal' => DataKapal::firstOrFail()]);
        $this->assertStringContainsString('wire:model.blur', $component->html());
        $this->assertStringContainsString('dirtyKeys[', $component->html());
        $this->assertStringContainsString('data-[unsaved=true]:bg-yellow-50', $component->html());
        $component->assertDontSee('data-unsaved="true"', false);
        $key = collect($component->get('drafts'))->keys()->first();
        $nama = $component->get("drafts.$key.nama");
        $component->call('addRow')->assertSee('data-unsaved="true"', false);
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey)->assertDontSee('data-unsaved="true"', false)
            ->set("drafts.$key.nama", $nama.' UI')->assertSet("drafts.$key.dirty", true)
            ->assertSee('data-draft-key="'.$key.'" data-unsaved="true"', false)
            ->call('saveRow', $key)->assertHasNoErrors()->assertDontSee('data-unsaved="true"', false);
        $this->assertSame($nama.' UI', ProjectVendor::findOrFail((int) substr($key, 4))->nama);
    }

    #[Test]
    public function inline_create_cancel_and_delete_work_per_row(): void
    {
        $ship = DataKapal::firstOrFail();
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('addRow');
        $key = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $lingkupId = VendorLingkup::where('nama', 'Piping System')->value('id');
        $component->set("drafts.$key.nama", '  PT Inline Vendor  ')
            ->set("drafts.$key.lingkup_id", $lingkupId)
            ->set("drafts.$key.bulan_ke", 1)
            ->set("drafts.$key.kontak", '021-999888')
            ->call('sortBy', 'kontak')
            ->assertSet("drafts.$key.nama", '  PT Inline Vendor  ')
            ->call('saveRow', $key)->assertHasNoErrors();
        $vendor = ProjectVendor::where('nama', 'PT Inline Vendor')->firstOrFail();
        $this->assertSame('Piping System', $vendor->lingkup->nama);
        $this->assertSame(1, $vendor->bulan_ke);
        $this->assertSame($ship->id, $vendor->data_kapal_id);
        $component->call('addRow');
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey);
        $this->assertArrayNotHasKey($newKey, $component->get('drafts'));
        $component->call('confirmDelete', $vendor->id)->assertSet('showDeleteModal', true)->call('delete');
        $this->assertNull(ProjectVendor::find($vendor->id));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'project_vendor', 'event' => 'deleted', 'subject_id' => $vendor->id]);
    }

    #[Test]
    public function sorting_is_whitelisted_and_search_is_scoped_to_the_ship(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $lingkupId = VendorLingkup::firstOrFail()->id;
        $service->saveVendor($ship, ['nama' => 'Zebra Vendor', 'lingkup_id' => $lingkupId, 'kontak' => 'zzz']);
        $service->saveVendor($ship, ['nama' => 'Alpha Vendor', 'lingkup_id' => $lingkupId, 'kontak' => 'aaa']);
        $ascending = $service->getVendors($ship, 'Vendor', 10, 'nama');
        $descending = $service->getVendors($ship, 'Vendor', 10, 'nama', 'desc');
        $invalid = $service->getVendors($ship, 'Vendor', 10, 'nama; DROP TABLE users');
        $this->assertSame('Alpha Vendor', $ascending->first()->nama);
        $this->assertSame('Zebra Vendor', $descending->first()->nama);
        $this->assertSame('Alpha Vendor', $invalid->first()->nama);
        $this->assertSame(0, $service->getVendors($ship, 'Vendor Kontak Khusus')->total());
        $this->assertSame(3, $service->getVendors(DataKapal::whereKeyNot($ship->id)->firstOrFail(), '')->total());
        $this->assertSame(1, $service->getVendors($ship, '3456-7890')->total());
    }

    #[Test]
    public function bulan_options_come_from_generated_plan_and_save_requires_a_valid_month(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $expected = KurvaSRencana::whereHas('workGroup', fn ($q) => $q->where('data_kapal_id', $ship->id))
            ->distinct()->orderBy('bulan_ke')->pluck('bulan_ke')->map(fn ($v) => (int) $v)->all();
        $this->assertNotEmpty($expected);
        $this->assertSame($expected, $service->getBulanKeIds($ship));
        $this->assertSame($expected, collect($service->getBulanKeOptions($ship))->pluck('value')->all());

        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('addRow');
        $key = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->set("drafts.$key.nama", 'Vendor Tanpa Bulan')->call('saveRow', $key)
            ->assertHasErrors(["drafts.$key.bulan_ke"]);
        $this->assertDatabaseMissing('project_vendor', ['nama' => 'Vendor Tanpa Bulan']);
        $component->set("drafts.$key.bulan_ke", 999)->call('saveRow', $key)
            ->assertHasErrors(["drafts.$key.bulan_ke"]);
        $this->assertDatabaseMissing('project_vendor', ['nama' => 'Vendor Tanpa Bulan']);
    }

    #[Test]
    public function monthly_word_lists_only_cumulative_vendors_without_kontak(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        // Kapal pertama (22 PAX) total 2 bulan — bulan_ke vendor ke-3 di-cap ke 2.
        $this->assertSame(3, $service->getVendorsUpToBulanGrouped($ship, 2)->flatten(1)->count());
        $this->assertSame(3, $service->getVendorsUpToBulanGrouped($ship, 3)->flatten(1)->count());
        $this->assertSame(1, $service->getVendorsUpToBulanGrouped($ship, 1)->flatten(1)->count());

        $template = tempnam(sys_get_temp_dir(), 'vendor-template-');
        $output = tempnam(sys_get_temp_dir(), 'vendor-output-');
        try {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('${tabel_vendor}');
            WordStyleService::prepare($word);
            IOFactory::createWriter($word, 'Word2007')->save($template);
            $processor = new TemplateProcessor($template);
            $report = new LaporanBulanan;
            $report->forceFill(['bulan_ke' => 2]);
            $report->setRelation('dataKapal', $ship);
            (new \ReflectionMethod(LaporanBulananWordService::class, 'fillVendor'))->invoke(app(LaporanBulananWordService::class), $processor, $report);
            $processor->saveAs($output);
            $zip = new \ZipArchive;
            $zip->open($output);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertStringContainsString('CV Baja Perkasa', $xml);
            $this->assertStringContainsString('PT Sumber Teknik Nusantara', $xml);
            $this->assertStringContainsString('PIPING SYSTEM', $xml);
            $this->assertStringContainsString('PT Marine Electricindo', $xml);
            $this->assertStringContainsString('ELECTRICAL INSTALLATION', $xml);
            $this->assertStringNotContainsString('0812-3456-7890', $xml);
            $this->assertStringNotContainsString('Kontak', $xml);
            $this->assertStringNotContainsString('${tabel_vendor}', $xml);
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
            ->test(ProjectVendorManagement::class, ['dataKapal' => $ship])->assertForbidden();
        $viewer = User::factory()->create(['owner_id' => $ship->owner_id]);
        $viewer->givePermissionTo('proyek_data_view');
        Livewire::actingAs($viewer)->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('addRow')->assertForbidden();
        Livewire::actingAs($viewer)->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('confirmDelete', ProjectVendor::firstOrFail()->id)->assertForbidden();
    }

    #[Test]
    public function vendor_cannot_be_read_or_written_to_another_ship(): void
    {
        $service = app(DataProyekService::class);
        $vendor = ProjectVendor::firstOrFail();
        $other = DataKapal::whereKeyNot($vendor->data_kapal_id)->firstOrFail();
        $this->expectException(ModelNotFoundException::class);
        $service->findVendor($other, $vendor->id);
    }

    #[Test]
    public function lingkup_catalog_crud_unique_guard_and_delete_in_use(): void
    {
        $service = app(DataProyekService::class);
        $lingkup = $service->saveVendorLingkup(VendorCatalog::Lingkup, '  HVAC   System ');
        $this->assertSame('HVAC System', $lingkup->nama);
        $this->assertDatabaseHas('vendor_lingkups', ['nama' => 'HVAC System']);

        $this->expectException(ValidationException::class);
        $service->saveVendorLingkup(VendorCatalog::Lingkup, 'hvac system');
    }

    #[Test]
    public function lingkup_in_use_cannot_be_deleted(): void
    {
        $service = app(DataProyekService::class);
        $lingkup = VendorLingkup::where('nama', 'Piping System')->firstOrFail();
        try {
            $service->deleteVendorLingkup(VendorCatalog::Lingkup, $lingkup->id);
            $this->fail('Lingkup terpakai seharusnya tidak bisa dihapus.');
        } catch (ValidationException) {
            $this->assertDatabaseHas('vendor_lingkups', ['id' => $lingkup->id]);
        }
    }

    #[Test]
    public function catalog_manage_requires_permission(): void
    {
        $ship = DataKapal::firstOrFail();
        $viewer = User::factory()->create(['owner_id' => $ship->owner_id]);
        $viewer->givePermissionTo('proyek_data_view');
        Livewire::actingAs($viewer)->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('manageCatalog', 'lingkup')->assertForbidden();
        $viewer->givePermissionTo('vendor_lingkups_view');
        Livewire::actingAs($viewer)->test(ProjectVendorManagement::class, ['dataKapal' => $ship])
            ->call('manageCatalog', 'lingkup')->assertSet('showCatalog', true)
            ->call('saveCatalog')->assertForbidden();
    }
}
