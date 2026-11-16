<?php

namespace App\Livewire\MasterData;

use App\Enums\DrawingStatus;
use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Enums\PengujianStatus;
use App\Enums\RapatJenis;
use App\Livewire\Traits\HasDataKapalFilter;
use App\Livewire\Traits\HasNotification;
use App\Models\DataKapal;
use App\Models\DrawingRegister;
use App\Models\Pengujian;
use App\Models\ProjectMaterial;
use App\Models\ProjectVendor;
use App\Models\Rapat;
use App\Services\DataProyekService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kelola data proyek per data kapal: drawing register, vendor,
 * material, pengujian, dan rapat — sumber data BAB 2 & 3 laporan bulanan.
 */
#[Layout('layouts.app', ['title' => 'Data Proyek'])]
class DataProyekManagement extends Component
{
    use AuthorizesRequests, HasDataKapalFilter, HasNotification, WithPagination;

    protected $paginationTheme = 'tailwind';

    public DataKapal $dataKapal;

    #[Url(as: 'tab')]
    public string $tab = 'drawing';

    public string $search = '';

    public int $perPage = 10;

    public bool $filterChanged = false;

    // Filter per tab (hanya yang relevan dengan tab aktif yang dipakai query)
    public string $statusProduksiFilter = '';

    public string $statusReviewMnoFilter = '';

    public string $statusKlasFilter = '';

    public string $materialKategoriFilter = '';

    public string $materialStatusFilter = '';

    public string $pengujianStatusFilter = '';

    public string $rapatJenisFilter = '';

    // Inline form state (drawing >3 field → bukan modal, form card di halaman)
    public bool $showForm = false;

    public bool $formEditMode = false;

    public ?int $formId = null;

    public array $form = [];

    // Delete modal
    public bool $showDeleteModal = false;

    public ?int $deletingId = null;

    public string $deletingLabel = '';

    private const PAGE_NAMES = ['drawingPage', 'vendorPage', 'materialPage', 'pengujianPage', 'rapatPage'];

    private const TABS = ['drawing', 'vendor', 'material', 'pengujian', 'rapat'];

    private const FILTER_PROPS = [
        'drawing' => ['statusProduksiFilter', 'statusReviewMnoFilter', 'statusKlasFilter'],
        'vendor' => [],
        'material' => ['materialKategoriFilter', 'materialStatusFilter'],
        'pengujian' => ['pengujianStatusFilter'],
        'rapat' => ['rapatJenisFilter'],
    ];

    public function mount(DataKapal $dataKapal): void
    {
        $this->authorize('viewAny', DrawingRegister::class);
        $this->authorizeDataKapalAccess($dataKapal);

        $this->dataKapal = $dataKapal;
        $this->tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'drawing';
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::TABS, true) || $this->tab === $tab) {
            return;
        }

        $this->tab = $tab;
        $this->cancelForm();
        $this->resetFilters();
    }

    // ─── FILTER ──────────────────────────────────────────────────────────

    public function updatingSearch(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingPerPage(): void
    {
        $this->resetAllPages();
    }

    public function updatingStatusProduksiFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingStatusReviewMnoFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingStatusKlasFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingMaterialKategoriFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingMaterialStatusFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingPengujianStatusFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function updatingRapatJenisFilter(): void
    {
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search', 'perPage',
            'statusProduksiFilter', 'statusReviewMnoFilter', 'statusKlasFilter',
            'materialKategoriFilter', 'materialStatusFilter',
            'pengujianStatusFilter', 'rapatJenisFilter',
        ]);
        $this->resetAllPages();
        $this->filterChanged = true;
    }

    private function resetAllPages(): void
    {
        foreach (self::PAGE_NAMES as $page) {
            $this->resetPage($page);
        }
    }

    // ─── FORM ────────────────────────────────────────────────────────────

    public function startCreate(): void
    {
        $this->authorize('create', $this->modelClass());

        $this->formId = null;
        $this->formEditMode = false;
        $this->form = $this->emptyForm();
        $this->showForm = true;
    }

    public function startEdit(int $id): void
    {
        $model = $this->findModel($id);
        $this->authorize('update', $model);

        $this->formId = $model->id;
        $this->formEditMode = true;
        $this->form = $this->formFromModel($model);
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->formEditMode = false;
        $this->formId = null;
        $this->form = [];
        $this->resetValidation();
    }

    public function save(DataProyekService $service): void
    {
        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->notifyValidationError($e);
            throw $e;
        }

        try {
            $model = $this->formEditMode ? $this->findModel($this->formId) : null;
            $this->authorize($this->formEditMode ? 'update' : 'create', $model ?? $this->modelClass());

            $service->{'save'.ucfirst($this->tab)}($this->dataKapal, $this->form, $model);

            $this->notifySuccess($this->entityLabel().' berhasil '.($this->formEditMode ? 'diupdate' : 'ditambahkan').'!');
            $this->cancelForm();
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak memiliki izin untuk melakukan aksi ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    // ─── DELETE ──────────────────────────────────────────────────────────

    public function confirmDelete(int $id): void
    {
        $model = $this->findModel($id);

        $this->deletingId = $model->id;
        $this->deletingLabel = match ($this->tab) {
            'drawing' => $model->nomor.' — '.$model->judul,
            'vendor' => $model->nama,
            'material' => $model->nama_item,
            'pengujian' => $model->nama_pengujian,
            'rapat' => $model->jenis->label().' '.$model->tanggal->format('d M Y'),
            default => '',
        };
        $this->showDeleteModal = true;
    }

    public function delete(DataProyekService $service): void
    {
        try {
            $model = $this->findModel($this->deletingId);
            $this->authorize('delete', $model);

            $service->delete($model);
            $this->notifySuccess($this->entityLabel().' berhasil dihapus!');
            $this->showDeleteModal = false;
            $this->deletingId = null;
        } catch (AuthorizationException $e) {
            $this->notifyError('Anda tidak dapat menghapus data ini.');
        } catch (\Exception $e) {
            $this->notifyError('Terjadi kesalahan: '.$e->getMessage());
        }
    }

    // ─── VALIDATION ──────────────────────────────────────────────────────

    protected function rules(): array
    {
        return match ($this->tab) {
            'drawing' => [
                'form.nomor' => 'required|string|max:100',
                'form.judul' => 'required|string|max:255',
                'form.status_produksi' => 'required|in:'.implode(',', DrawingStatus::values()),
                'form.status_review_mno' => 'required|in:'.implode(',', DrawingStatus::values()),
                'form.status_approval_klas' => 'required|in:'.implode(',', DrawingStatus::values()),
                'form.mno_comment_open' => 'nullable|integer|min:0|max:9999',
                'form.mno_comment_closed' => 'nullable|integer|min:0|max:9999',
                'form.mno_comment_informasi' => 'nullable|integer|min:0|max:9999',
                'form.klas_comment_open' => 'nullable|integer|min:0|max:9999',
                'form.klas_comment_closed' => 'nullable|integer|min:0|max:9999',
                'form.klas_comment_informasi' => 'nullable|integer|min:0|max:9999',
                'form.keterangan' => 'nullable|string|max:1000',
            ],
            'vendor' => [
                'form.nama' => 'required|string|max:255',
                'form.lingkup_pekerjaan' => 'nullable|string|max:255',
                'form.kontak' => 'nullable|string|max:255',
                'form.keterangan' => 'nullable|string|max:1000',
            ],
            'material' => [
                'form.kategori' => 'required|in:'.implode(',', MaterialKategori::values()),
                'form.nama_item' => 'required|string|max:255',
                'form.spesifikasi' => 'nullable|string|max:255',
                'form.jumlah' => 'nullable|numeric|min:0',
                'form.satuan' => 'nullable|string|max:50',
                'form.status' => 'required|in:'.implode(',', MaterialStatus::values()),
                'form.tanggal' => 'nullable|date',
                'form.keterangan' => 'nullable|string|max:1000',
            ],
            'pengujian' => [
                'form.nama_pengujian' => 'required|string|max:255',
                'form.tanggal' => 'nullable|date',
                'form.status' => 'required|in:'.implode(',', PengujianStatus::values()),
                'form.hasil' => 'nullable|string|max:255',
                'form.keterangan' => 'nullable|string|max:1000',
            ],
            'rapat' => [
                'form.tanggal' => 'required|date',
                'form.jenis' => 'required|in:'.implode(',', RapatJenis::values()),
                'form.lokasi' => 'nullable|string|max:255',
                'form.topik' => 'nullable|string|max:255',
            ],
            default => [],
        };
    }

    public function validationAttributes(): array
    {
        return [
            'form.nomor' => 'nomor drawing',
            'form.judul' => 'judul drawing',
            'form.status_produksi' => 'status produksi',
            'form.status_review_mno' => 'status review MNO',
            'form.status_approval_klas' => 'status approval Klas',
            'form.mno_comment_open' => 'comment MNO open',
            'form.mno_comment_closed' => 'comment MNO closed',
            'form.mno_comment_informasi' => 'comment MNO informasi',
            'form.klas_comment_open' => 'comment Klas open',
            'form.klas_comment_closed' => 'comment Klas closed',
            'form.klas_comment_informasi' => 'comment Klas informasi',
            'form.keterangan' => 'keterangan',
            'form.nama' => 'nama vendor',
            'form.lingkup_pekerjaan' => 'lingkup pekerjaan',
            'form.kontak' => 'kontak',
            'form.kategori' => 'kategori material',
            'form.nama_item' => 'nama item',
            'form.spesifikasi' => 'spesifikasi',
            'form.jumlah' => 'jumlah',
            'form.satuan' => 'satuan',
            'form.status' => 'status',
            'form.tanggal' => 'tanggal',
            'form.nama_pengujian' => 'nama pengujian',
            'form.hasil' => 'hasil pengujian',
            'form.jenis' => 'jenis rapat',
            'form.lokasi' => 'lokasi rapat',
            'form.topik' => 'topik rapat',
        ];
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────

    private function modelClass(): string
    {
        return match ($this->tab) {
            'drawing' => DrawingRegister::class,
            'vendor' => ProjectVendor::class,
            'material' => ProjectMaterial::class,
            'pengujian' => Pengujian::class,
            'rapat' => Rapat::class,
            default => DrawingRegister::class,
        };
    }

    private function entityLabel(): string
    {
        return match ($this->tab) {
            'drawing' => 'Drawing',
            'vendor' => 'Vendor',
            'material' => 'Material',
            'pengujian' => 'Pengujian',
            'rapat' => 'Rapat',
            default => 'Data',
        };
    }

    private function findModel(?int $id): Model
    {
        return $this->modelClass()::where('data_kapal_id', $this->dataKapal->id)->findOrFail($id);
    }

    private function emptyForm(): array
    {
        return match ($this->tab) {
            'drawing' => [
                'nomor' => '', 'judul' => '',
                'status_produksi' => DrawingStatus::Belum->value,
                'status_review_mno' => DrawingStatus::Belum->value,
                'status_approval_klas' => DrawingStatus::Belum->value,
                'mno_comment_open' => 0, 'mno_comment_closed' => 0, 'mno_comment_informasi' => 0,
                'klas_comment_open' => 0, 'klas_comment_closed' => 0, 'klas_comment_informasi' => 0,
                'keterangan' => '',
            ],
            'vendor' => ['nama' => '', 'lingkup_pekerjaan' => '', 'kontak' => '', 'keterangan' => ''],
            'material' => [
                'kategori' => MaterialKategori::Lainnya->value,
                'nama_item' => '', 'spesifikasi' => '', 'jumlah' => null, 'satuan' => '',
                'status' => MaterialStatus::Direncanakan->value,
                'tanggal' => '', 'keterangan' => '',
            ],
            'pengujian' => [
                'nama_pengujian' => '', 'tanggal' => '',
                'status' => PengujianStatus::Direncanakan->value,
                'hasil' => '', 'keterangan' => '',
            ],
            'rapat' => [
                'tanggal' => now()->format('Y-m-d'),
                'jenis' => RapatJenis::Mingguan->value,
                'lokasi' => '', 'topik' => '',
            ],
            default => [],
        };
    }

    private function formFromModel(Model $model): array
    {
        $data = $model->toArray();
        $form = $this->emptyForm();

        foreach ($form as $key => $default) {
            if (array_key_exists($key, $data)) {
                $form[$key] = $data[$key] ?? $default;
            }
        }

        // Enum & tanggal → string mentah untuk input form
        foreach (['status_produksi', 'status_review_mno', 'status_approval_klas', 'kategori', 'status', 'jenis'] as $key) {
            if (isset($form[$key]) && $model->{$key} instanceof \BackedEnum) {
                $form[$key] = $model->{$key}->value;
            }
        }
        if (isset($form['tanggal']) && $model->tanggal) {
            $form['tanggal'] = $model->tanggal->format('Y-m-d');
        }

        return $form;
    }

    // ─── RENDER ──────────────────────────────────────────────────────────

    public function render(DataProyekService $service)
    {
        $items = match ($this->tab) {
            'drawing' => $service->getDrawings($this->dataKapal, $this->search, $this->perPage, [
                'status_produksi' => $this->statusProduksiFilter,
                'status_review_mno' => $this->statusReviewMnoFilter,
                'status_approval_klas' => $this->statusKlasFilter,
            ]),
            'vendor' => $service->getVendors($this->dataKapal, $this->search, $this->perPage),
            'material' => $service->getMaterials($this->dataKapal, $this->search, $this->perPage, [
                'kategori' => $this->materialKategoriFilter,
                'status' => $this->materialStatusFilter,
            ]),
            'pengujian' => $service->getPengujians($this->dataKapal, $this->search, $this->perPage, [
                'status' => $this->pengujianStatusFilter,
            ]),
            'rapat' => $service->getRapats($this->dataKapal, $this->search, $this->perPage, [
                'jenis' => $this->rapatJenisFilter,
            ]),
            default => $service->getDrawings($this->dataKapal, $this->search, $this->perPage),
        };

        if ($this->filterChanged) {
            $this->notifyInfo("Ditemukan {$items->total()} data {$this->entityLabel()}.");
            $this->filterChanged = false;
        }

        return view('livewire.master-data.data-proyek-management', [
            'items' => $items,
            'activeFilters' => self::FILTER_PROPS[$this->tab] ?? [],
            'drawingStatuses' => DrawingStatus::cases(),
            'materialKategoris' => MaterialKategori::cases(),
            'materialStatuses' => MaterialStatus::cases(),
            'pengujianStatuses' => PengujianStatus::cases(),
            'rapatJenises' => RapatJenis::cases(),
        ]);
    }
}
