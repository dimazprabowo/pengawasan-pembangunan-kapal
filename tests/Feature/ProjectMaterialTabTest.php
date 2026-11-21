<?php

namespace Tests\Feature;

use App\Enums\MaterialCatalog;
use App\Livewire\MasterData\DataProyekManagement;
use App\Livewire\MasterData\ProjectMaterialManagement;
use App\Models\DataKapal;
use App\Models\LaporanBulanan;
use App\Models\MaterialCategory;
use App\Models\MaterialStatus;
use App\Models\ProjectMaterial;
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

class ProjectMaterialTabTest extends TestCase
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
    public function material_tab_embeds_the_inline_register_component(): void
    {
        Livewire::actingAs(User::firstOrFail())
            ->test(DataProyekManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('setTab', 'material')
            ->assertSeeLivewire('master-data.project-material-management');
    }

    #[Test]
    public function rows_render_as_editable_inputs_and_mark_unsaved_until_saved_or_cancelled(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectMaterialManagement::class, ['dataKapal' => DataKapal::firstOrFail()]);
        $this->assertStringContainsString('wire:model.blur', $component->html());
        $this->assertStringContainsString('dirtyKeys[', $component->html());
        $this->assertStringContainsString('data-[unsaved=true]:bg-yellow-50', $component->html());
        $component->assertDontSee('data-unsaved="true"', false);
        $key = collect($component->get('drafts'))->keys()->first();
        $nama = $component->get("drafts.$key.nama_item");
        $component->call('addRow')->assertSee('data-unsaved="true"', false);
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey)->assertDontSee('data-unsaved="true"', false)
            ->set("drafts.$key.nama_item", $nama.' UI')->assertSet("drafts.$key.dirty", true)
            ->assertSee('data-draft-key="'.$key.'" data-unsaved="true"', false)
            ->call('saveRow', $key)->assertHasNoErrors()->assertDontSee('data-unsaved="true"', false);
        $this->assertSame($nama.' UI', ProjectMaterial::findOrFail((int) substr($key, 4))->nama_item);
    }

    #[Test]
    public function inline_create_cancel_and_delete_work_per_row(): void
    {
        $ship = DataKapal::firstOrFail();
        $categoryId = MaterialCategory::firstOrFail()->id;
        $statusId = MaterialStatus::firstOrFail()->id;
        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectMaterialManagement::class, ['dataKapal' => $ship])
            ->call('addRow');
        $key = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->set("drafts.$key.nama_item", '  Inline Material  ')
            ->set("drafts.$key.material_category_id", $categoryId)
            ->set("drafts.$key.material_status_id", $statusId)
            ->set("drafts.$key.jumlah", 12.5)
            ->set("drafts.$key.satuan", 'Ton')
            ->call('sortBy', 'nama_item')
            ->assertSet("drafts.$key.nama_item", '  Inline Material  ')
            ->call('saveRow', $key)->assertHasNoErrors();
        $material = ProjectMaterial::where('nama_item', 'Inline Material')->firstOrFail();
        $this->assertSame($categoryId, $material->material_category_id);
        $this->assertSame($statusId, $material->material_status_id);
        $this->assertSame($ship->id, $material->data_kapal_id);
        $component->call('addRow');
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey);
        $this->assertArrayNotHasKey($newKey, $component->get('drafts'));
        $component->call('confirmDelete', $material->id)->assertSet('showDeleteModal', true)->call('delete');
        $this->assertNull(ProjectMaterial::find($material->id));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'project_material', 'event' => 'deleted', 'subject_id' => $material->id]);
    }

    #[Test]
    public function sorting_is_whitelisted_and_search_is_scoped_to_the_ship(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $base = ['material_category_id' => MaterialCategory::firstOrFail()->id, 'material_status_id' => MaterialStatus::firstOrFail()->id];
        $service->saveMaterial($ship, $base + ['nama_item' => 'Zebra Item']);
        $service->saveMaterial($ship, $base + ['nama_item' => 'Alpha Item']);
        $ascending = $service->getMaterials($ship, 'Item', 10, [], 'nama_item');
        $descending = $service->getMaterials($ship, 'Item', 10, [], 'nama_item', 'desc');
        $invalid = $service->getMaterials($ship, 'Item', 10, [], 'nama_item; DROP TABLE users');
        $this->assertSame('Alpha Item', $ascending->first()->nama_item);
        $this->assertSame('Zebra Item', $descending->first()->nama_item);
        $this->assertSame('Alpha Item', $invalid->first()->nama_item);
        $this->assertSame(0, $service->getMaterials($ship, 'Item Kontak Khusus')->total());
        $this->assertSame(0, $service->getMaterials(DataKapal::whereKeyNot($ship->id)->firstOrFail(), 'Alpha Item')->total());
        $this->assertSame(1, $service->getMaterials($ship, 'Main Engine')->total());
    }

    #[Test]
    public function material_catalogs_can_be_created_renamed_and_used_ones_are_protected(): void
    {
        $service = app(DataProyekService::class);
        $this->assertCount(3, $service->materialCatalogs(MaterialCatalog::Category));
        $this->assertCount(4, $service->materialCatalogs(MaterialCatalog::Status));

        $category = $service->saveMaterialCatalog(MaterialCatalog::Category, 'Outfitting');
        $this->assertDatabaseHas('material_categories', ['id' => $category->id, 'nama' => 'Outfitting']);
        $service->saveMaterialCatalog(MaterialCatalog::Category, 'Outfitting Premium', $category->id);
        $this->assertSame('Outfitting Premium', $category->fresh()->nama);
        try {
            $service->saveMaterialCatalog(MaterialCatalog::Category, 'outfitting premium');
            $this->fail('Duplicate catalog name should be rejected.');
        } catch (ValidationException) {
        }

        $usedId = ProjectMaterial::firstOrFail()->material_category_id;
        try {
            $service->deleteMaterialCatalog(MaterialCatalog::Category, $usedId);
            $this->fail('Used catalog should not be deletable.');
        } catch (ValidationException) {
        }
        $service->deleteMaterialCatalog(MaterialCatalog::Category, $category->id);
        $this->assertDatabaseMissing('material_categories', ['id' => $category->id]);

        $component = Livewire::actingAs(User::firstOrFail())->test(ProjectMaterialManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('manageCatalog', 'category')->assertSet('showCatalog', true)
            ->set('catalogName', 'Katalog UI')->call('saveCatalog')->assertHasNoErrors();
        $this->assertDatabaseHas('material_categories', ['nama' => 'Katalog UI']);
        $component->call('confirmDelete', $usedId, 'category')->assertSet('showDeleteModal', true)->call('delete')
            ->assertSet('showDeleteModal', false);
        $this->assertDatabaseHas('material_categories', ['id' => $usedId]);
    }

    #[Test]
    public function monthly_word_lists_cumulative_materials_grouped_by_category(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DataProyekService::class);
        $cutoff = $ship->tanggal_mulai_pelaksanaan->copy()->addDays(35);
        $this->assertSame(4, $service->getMaterialsGrouped($ship, $cutoff)->flatten(1)->count());
        $this->assertSame(5, $service->getMaterialsGrouped($ship, null)->flatten(1)->count());
        $this->assertSame(5, $service->getMaterialsGrouped($ship, $ship->tanggal_mulai_pelaksanaan->copy()->addDays(50))->flatten(1)->count());

        $template = tempnam(sys_get_temp_dir(), 'material-template-');
        $output = tempnam(sys_get_temp_dir(), 'material-output-');
        try {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('${tabel_material}');
            WordStyleService::prepare($word);
            IOFactory::createWriter($word, 'Word2007')->save($template);
            $processor = new TemplateProcessor($template);
            $report = new LaporanBulanan;
            $report->forceFill(['periode_selesai' => $cutoff]);
            $report->setRelation('dataKapal', $ship);
            (new \ReflectionMethod(LaporanBulananWordService::class, 'fillMaterial'))->invoke(app(LaporanBulananWordService::class), $processor, $report);
            $processor->saveAs($output);
            $zip = new \ZipArchive;
            $zip->open($output);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertStringContainsString('Steel Plate AH36 12mm', $xml);
            $this->assertStringContainsString('STEEL PLATE', $xml);
            $this->assertStringContainsString('Main Engine', $xml);
            $this->assertStringContainsString('SYSTEM / KOMPONEN', $xml);
            $this->assertStringNotContainsString('Welding Consumable', $xml);
            $this->assertStringNotContainsString('${tabel_material}', $xml);
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
            ->test(ProjectMaterialManagement::class, ['dataKapal' => $ship])->assertForbidden();
        $viewer = User::factory()->create(['owner_id' => $ship->owner_id]);
        $viewer->givePermissionTo('proyek_data_view');
        Livewire::actingAs($viewer)->test(ProjectMaterialManagement::class, ['dataKapal' => $ship])
            ->call('addRow')->assertForbidden();
        Livewire::actingAs($viewer)->test(ProjectMaterialManagement::class, ['dataKapal' => $ship])
            ->call('confirmDelete', ProjectMaterial::firstOrFail()->id)->assertForbidden();
    }

    #[Test]
    public function material_cannot_be_read_or_written_to_another_ship(): void
    {
        $service = app(DataProyekService::class);
        $material = ProjectMaterial::firstOrFail();
        $other = DataKapal::whereKeyNot($material->data_kapal_id)->firstOrFail();
        $this->expectException(ModelNotFoundException::class);
        $service->findMaterial($other, $material->id);
    }
}
