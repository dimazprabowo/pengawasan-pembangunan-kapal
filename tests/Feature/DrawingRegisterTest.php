<?php

namespace Tests\Feature;

use App\Enums\DrawingCatalog;
use App\Livewire\MasterData\DrawingRegisterManagement;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Models\DrawingRevision;
use App\Models\DrawingStatus;
use App\Models\DrawingType;
use App\Models\LaporanBulanan;
use App\Models\User;
use App\Services\DrawingRegisterService;
use App\Services\LaporanBulananWordService;
use App\Services\WordStyleService;
use Carbon\Carbon;
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

class DrawingRegisterTest extends TestCase
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
    public function shared_action_buttons_follow_livewire_request_loading(): void
    {
        $this->blade('<x-cancel-button wire:click="closeCatalog" target="closeCatalog" />')
            ->assertSee('wire:target="closeCatalog"', false)
            ->assertSee('wire:loading.attr="disabled"', false)
            ->assertDontSee('setTimeout', false);
        $this->blade('<x-loading-button target="saveCatalog" icon="legacy">Simpan</x-loading-button>')
            ->assertSee('Simpan')
            ->assertSee('wire:target="saveCatalog"', false);
    }

    #[Test]
    public function unsaved_rows_are_highlighted_until_saved_or_cancelled_without_a_text_badge(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(DrawingRegisterManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->assertSee('Sudah tersimpan')
            ->assertDontSee('data-unsaved="true"', false);
        $key = collect($component->get('drafts'))->keys()->first();
        $name = $component->get("drafts.$key.nama");
        $component->call('addRow')->assertSee('data-unsaved="true"', false);
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->call('cancelRow', $newKey)->assertDontSee('data-unsaved="true"', false)
            ->set("drafts.$key.nama", $name.' UI')->assertSet("drafts.$key.dirty", true)
            ->assertSee('data-draft-key="'.$key.'" data-unsaved="true"', false)
            ->call('saveRow', $key)->assertHasNoErrors()->assertDontSee('data-unsaved="true"', false)
            ->set("drafts.$key.nama", $name.' Belum Tersimpan')->assertSee('data-unsaved="true"', false)
            ->call('cancelRow', $key)->assertDontSee('data-unsaved="true"', false);
        $revisionId = (int) substr($key, 4);
        // revisi ter-seed berstatus in-progress: tambah revisi harus ditolak
        $component->call('addRevision', $revisionId)->assertDontSee('data-unsaved="true"', false);
        DrawingRevision::whereKey($revisionId)
            ->update(['drawing_status_id' => DrawingStatus::where('nama', \App\Enums\DrawingStatus::Seen->value)->value('id')]);
        $component->call('addRevision', $revisionId)->assertSee('data-unsaved="true"', false);
        $this->assertStringContainsString('dirtyKeys[', $component->html());
        $this->assertStringContainsString('data-[unsaved=true]:bg-yellow-50', $component->html());
        $document = new \DOMDocument;
        $document->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//table//text()[contains(.,"Belum disimpan")]')->length);
        $contractStatus = $xpath->query('//form[@*[name()="wire:submit"]="saveContract"]//p[contains(@x-text,"dirtyKeys.contract")]')->item(0);
        $this->assertNotNull($contractStatus);
        $this->assertStringContainsString('Belum disimpan', $contractStatus->getAttribute('x-text'));
        $this->assertStringContainsString('text-green-600', $contractStatus->getAttribute('class'));
        $this->assertStringContainsString('text-yellow-600', $contractStatus->getAttribute(':class'));
    }

    #[Test]
    public function toolbar_buttons_match_and_catalog_actions_share_a_footer_below_the_field(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(DrawingRegisterManagement::class, ['dataKapal' => DataKapal::firstOrFail()]);
        $this->assertSame(2, substr_count($component->html(), 'w-full lg:w-36 h-10 shrink-0'));
        foreach (['type', 'status'] as $kind) {
            $component->call('manageCatalog', $kind);
            $document = new \DOMDocument;
            $document->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($document);
            $form = $xpath->query('//form[@*[name()="wire:submit"]="saveCatalog"]')->item(0);
            $this->assertNotNull($form);
            $save = $xpath->query('.//button[@type="submit"]', $form)->item(0);
            $cancel = $xpath->query('.//button[@*[name()="wire:click"]="closeCatalog"]', $form)->item(0);
            $this->assertNotNull($save);
            $this->assertNotNull($cancel);
            $this->assertTrue($save->parentNode->isSameNode($cancel->parentNode));
            $this->assertStringContainsString('flex items-center', $save->parentNode->getAttribute('class'));
            $this->assertSame('catalog-name', $save->parentNode->previousElementSibling->getElementsByTagName('input')->item(0)?->getAttribute('id'));
            $component->call('closeCatalog');
        }
    }

    private function statusId(string $name): int
    {
        return DrawingStatus::where('nama', $name)->firstOrFail()->id;
    }

    public function test_revisions_are_cumulative_and_exclude_future_periods(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DrawingRegisterService::class);
        $data = ['nama' => 'Drawing Historis', 'drawing_type_id' => DrawingType::firstOrFail()->id, 'revisi' => 0, 'tanggal' => '2026-03-10', 'drawing_status_id' => $this->statusId('in-progress')];
        $first = $service->saveRevision($ship, $data);
        $service->saveRevision($ship, array_replace($data, ['revisi' => 1, 'tanggal' => '2026-04-08', 'drawing_status_id' => $this->statusId('examined')]), $first->drawing_register_id);
        $march = $service->historyUpTo($ship, Carbon::parse('2026-03-31'))->where('drawing_register_id', $first->drawing_register_id);
        $april = $service->historyUpTo($ship, Carbon::parse('2026-04-30'))->where('drawing_register_id', $first->drawing_register_id);
        $this->assertCount(1, $march);
        $this->assertSame('in-progress', $march->first()->status->nama);
        $this->assertCount(2, $april);
        $this->assertSame(1, $april->last()->revisi);
    }

    public function test_inline_create_update_and_sort_preserve_unsaved_rows(): void
    {
        $ship = DataKapal::firstOrFail();
        $admin = User::firstOrFail();
        $component = Livewire::actingAs($admin)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])
            ->call('addRow');
        $key = collect($component->get('drafts'))->keys()->first(fn ($key) => str_starts_with($key, 'new-'));
        $component->set("drafts.$key.nama", 'Gambar Inline')
            ->set("drafts.$key.drawing_type_id", DrawingType::firstOrFail()->id)
            ->set("drafts.$key.tanggal", '2026-03-15')
            ->call('sortBy', 'nama')
            ->assertSet("drafts.$key.nama", 'Gambar Inline')
            ->call('saveRow', $key)->assertHasNoErrors();
        $drawing = DrawingRegister::where('nama', 'Gambar Inline')->firstOrFail();
        $this->assertCount(1, $drawing->revisions);
        $revisionId = $drawing->revisions->first()->id;
        $component->set("drafts.row-$revisionId.drawing_status_id", $this->statusId('seen'))->call('saveRow', 'row-'.$revisionId)->assertHasNoErrors();
        $this->assertSame('seen', $drawing->revisions()->first()->status->nama);
        $component->call('addRevision', $revisionId);
        $newKey = collect($component->get('drafts'))->keys()->first(fn ($k) => str_starts_with($k, 'new-'));
        $component->set("drafts.$newKey.tanggal", '2026-04-01')->call('saveRow', $newKey)->assertHasNoErrors();
        $this->assertCount(2, $drawing->fresh()->revisions);
    }

    public function test_unauthorized_users_cannot_open_editor(): void
    {
        $user = User::factory()->create();
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => DataKapal::firstOrFail()])->assertForbidden();
    }

    public function test_revision_cannot_be_written_to_another_ship(): void
    {
        $service = app(DrawingRegisterService::class);
        $drawing = DrawingRegister::firstOrFail();
        $ship = DataKapal::whereKeyNot($drawing->data_kapal_id)->firstOrFail();
        $this->expectException(ModelNotFoundException::class);
        $service->saveRevision($ship, ['nama' => 'Tidak boleh', 'drawing_type_id' => DrawingType::firstOrFail()->id, 'revisi' => 99, 'tanggal' => '2026-04-01', 'drawing_status_id' => $this->statusId('seen')], $drawing->id);
    }

    public function test_monthly_word_contains_all_history_up_to_cutoff(): void
    {
        $variables = (new TemplateProcessor(storage_path('app/private/templates/laporan-bulanan/template-laporan-bulanan.docx')))->getVariables();
        $this->assertContains('nomor_kontrak_gambar', $variables);
        $this->assertContains('tabel_drawing', $variables);
        foreach (['tabel_drawing_galangan', 'tabel_drawing_mno', 'tabel_drawing_klas'] as $obsolete) {
            $this->assertNotContains($obsolete, $variables);
        }
        $ship = DataKapal::firstOrFail();
        app(DrawingRegisterService::class)->saveContract($ship, 'DRAW-WORD-2026');
        $service = app(DrawingRegisterService::class);
        $data = ['nama' => 'Word Drawing & History', 'drawing_type_id' => DrawingType::firstOrFail()->id, 'revisi' => 0, 'tanggal' => '2026-03-10', 'drawing_status_id' => $this->statusId('in-progress')];
        $first = $service->saveRevision($ship, $data);
        $service->saveRevision($ship, array_replace($data, ['revisi' => 1, 'tanggal' => '2026-04-10', 'drawing_status_id' => $this->statusId('examined')]), $first->drawing_register_id);
        $template = tempnam(sys_get_temp_dir(), 'drawing-template-');
        $output = tempnam(sys_get_temp_dir(), 'drawing-output-');
        try {
            Settings::setOutputEscapingEnabled(true);
            $word = new PhpWord;
            $section = $word->addSection();
            $section->addText('${nomor_kontrak_gambar}');
            $section->addText('${tabel_drawing}');
            WordStyleService::prepare($word);
            IOFactory::createWriter($word, 'Word2007')->save($template);
            $processor = new TemplateProcessor($template);
            $report = new LaporanBulanan;
            $report->forceFill(['periode_selesai' => '2026-03-31', 'tanggal_laporan' => '2026-03-31']);
            $report->setRelation('dataKapal', $ship);
            (new \ReflectionMethod(LaporanBulananWordService::class, 'fillDrawing'))->invoke(app(LaporanBulananWordService::class), $processor, $report);
            $processor->saveAs($output);
            $zip = new \ZipArchive;
            $zip->open($output);
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            $this->assertStringContainsString('DRAW-WORD-2026', $xml);
            $this->assertStringContainsString('Word Drawing &amp; History', $xml);
            $this->assertStringContainsString('In-Progress', $xml);
            $this->assertStringNotContainsString('Examined', $xml);
            $this->assertStringNotContainsString('${tabel_drawing}', $xml);
        } finally {
            unlink($template);
            unlink($output);
        }
    }

    public function test_view_only_permission_cannot_create_or_manage_shared_types(): void
    {
        $ship = DataKapal::firstOrFail();
        $user = User::factory()->create(['owner_id' => $ship->owner_id]);
        $user->givePermissionTo('proyek_data_view');
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])->call('addRow')->assertForbidden();
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])->call('manageCatalog', 'type')->assertForbidden();
    }

    public function test_contract_is_saved_per_ship_and_revision_deletion_keeps_other_history(): void
    {
        $ship = DataKapal::firstOrFail();
        $component = Livewire::actingAs(User::firstOrFail())->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])
            ->set('contractNumber', '  DRAW-2026-01  ')->call('saveContract')->assertHasNoErrors();
        $this->assertSame('DRAW-2026-01', $ship->fresh()->nomor_kontrak_gambar);
        $this->assertFalse(DataKapal::whereKeyNot($ship->id)->where('nomor_kontrak_gambar', 'DRAW-2026-01')->exists());
        $revision = $ship->drawingRegisters()->firstOrFail()->revisions()->firstOrFail();
        $component->call('confirmDelete', $revision->id)->assertSet('showDeleteModal', true)->call('delete');
        $this->assertDatabaseMissing('drawing_revisions', ['id' => $revision->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'drawing_revision', 'event' => 'deleted', 'subject_id' => $revision->id]);
    }

    public function test_sorting_whitelist_and_filters_work_on_server(): void
    {
        $ship = DataKapal::firstOrFail();
        $service = app(DrawingRegisterService::class);
        $type = DrawingType::firstOrFail();
        foreach (['Zebra Gambar', 'Alpha Gambar'] as $name) {
            $service->saveRevision($ship, ['nama' => $name, 'drawing_type_id' => $type->id, 'revisi' => 0, 'tanggal' => '2026-03-01', 'drawing_status_id' => $this->statusId('seen')]);
        }
        $ascending = $service->getFiltered($ship, 'Gambar', ['status' => $this->statusId('seen'), 'type' => $type->id], sortField: 'nama');
        $descending = $service->getFiltered($ship, 'Gambar', ['status' => $this->statusId('seen')], sortField: 'nama', sortDir: 'desc');
        $invalid = $service->getFiltered($ship, 'Gambar', ['status' => $this->statusId('seen'), 'tanggal' => '2026-99-99'], sortField: 'nama; DROP TABLE users');
        $this->assertSame('Alpha Gambar', $ascending->first()->drawing->nama);
        $this->assertSame('Zebra Gambar', $descending->first()->drawing->nama);
        $this->assertSame('Alpha Gambar', $invalid->first()->drawing->nama);
        $this->assertCount(0, $service->getFiltered($ship, 'Gambar', ['tanggal' => '2026-02-28']));
    }

    public function test_shared_types_can_be_created_renamed_and_deleted_with_permission(): void
    {
        $component = Livewire::actingAs(User::firstOrFail())->test(DrawingRegisterManagement::class, ['dataKapal' => DataKapal::firstOrFail()])
            ->call('manageCatalog', 'type')->set('catalogName', 'Hull Structure')->call('saveCatalog')->assertHasNoErrors();
        $type = DrawingType::where('nama', 'Hull Structure')->firstOrFail();
        $component->call('editCatalog', $type->id)->set('catalogName', 'Structure')->call('saveCatalog')->assertHasNoErrors();
        $this->assertSame('Structure', $type->fresh()->nama);
        $component->call('confirmDelete', $type->id, 'type')->call('delete');
        $this->assertDatabaseMissing('drawing_types', ['id' => $type->id]);
    }

    #[Test]
    public function dynamic_status_catalog_supports_form_modes_and_protects_history(): void
    {
        $ship = DataKapal::firstOrFail();
        $component = Livewire::actingAs(User::firstOrFail())->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])
            ->call('manageCatalog', 'status')->set('catalogName', 'Waiting Review')->call('saveCatalog')->assertHasNoErrors();
        $status = DrawingStatus::where('nama', 'Waiting Review')->firstOrFail();
        $component->call('editCatalog', $status->id)->assertSet('editingCatalogId', $status->id)->assertSet('catalogName', 'Waiting Review')
            ->call('newCatalog')->assertSet('editingCatalogId', null)->assertSet('catalogName', '')
            ->call('editCatalog', $status->id)->set('catalogName', 'Pending Review')->call('saveCatalog')->assertHasNoErrors();
        $this->assertSame('Pending Review', $status->fresh()->nama);
        $component->call('confirmDelete', $status->id, 'status')->call('delete');
        $this->assertDatabaseMissing('drawing_statuses', ['id' => $status->id]);
        $service = app(DrawingRegisterService::class);
        $used = $service->saveCatalog(DrawingCatalog::Status, 'Status Khusus');
        $revision = $service->saveRevision($ship, ['nama' => 'Drawing Status Dinamis', 'drawing_type_id' => DrawingType::firstOrFail()->id, 'drawing_status_id' => $used->id, 'revisi' => 0, 'tanggal' => '2026-03-01']);
        $component->call('confirmDelete', $used->id, 'status')->call('delete')->assertSet('showDeleteModal', false);
        $this->assertDatabaseHas('drawing_statuses', ['id' => $used->id]);
        $this->assertSame('Status Khusus', $service->historyUpTo($ship, Carbon::parse('2026-03-31'))->firstWhere('id', $revision->id)->status->label());
        $this->assertDatabaseHas('activity_log', ['log_name' => 'drawing_status', 'event' => 'created', 'subject_id' => $used->id]);
        $this->expectException(ValidationException::class);
        $service->saveCatalog(DrawingCatalog::Status, ' status   khusus ');
    }

    #[Test]
    public function status_catalog_has_independent_permission_gates(): void
    {
        $ship = DataKapal::firstOrFail();
        $user = User::factory()->create(['owner_id' => $ship->owner_id]);
        $user->givePermissionTo('proyek_data_view');
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])->call('manageCatalog', 'status')->assertForbidden();
        $user->givePermissionTo('drawing_statuses_view');
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])
            ->call('manageCatalog', 'status')->assertSet('showCatalog', true)->call('newCatalog')->assertForbidden();
        Livewire::actingAs($user)->test(DrawingRegisterManagement::class, ['dataKapal' => $ship])
            ->call('manageCatalog', 'status')->set('catalogName', 'Tidak Boleh')->call('saveCatalog')->assertForbidden();
    }

    public function test_used_type_cannot_be_deleted_and_duplicate_revision_is_rejected(): void
    {
        $service = app(DrawingRegisterService::class);
        $drawing = DrawingRegister::firstOrFail();
        try {
            $service->deleteCatalog(DrawingCatalog::Type, $drawing->drawing_type_id);
            $this->fail('Jenis yang digunakan tidak boleh dihapus.');
        } catch (ValidationException) {
            $this->assertNotNull($drawing->type->fresh());
        }
        $revision = $drawing->revisions->first();
        $this->expectException(ValidationException::class);
        $service->saveRevision($drawing->dataKapal, ['nama' => $drawing->nama, 'drawing_type_id' => $drawing->drawing_type_id, 'revisi' => $revision->revisi, 'tanggal' => $revision->tanggal->toDateString(), 'drawing_status_id' => $revision->drawing_status_id], $drawing->id);
    }
}
