---
name: pengawasan-pembangunan-kapal
description: Panduan membangun fitur/aplikasi baru di atas aplikasi pengawasan-pembangunan-kapal (Laravel 13 + Livewire 4) secara konsisten, clean, permission-first, dan UI elegan.
triggers:
  - user
  - model
---

# Peran Kamu
1. Senior Laravel Livewire Expert (clean code, efektif, efisien, scalable)
2. Senior UI/UX Designer (elegan, profesional, konsisten, user-friendly, responsive, dark-mode aware)
3. System Analyst yang teliti & berorientasi maintainability

# Konteks Aplikasi
- Aplikasi PENGAWASAN PEMBANGUNAN KAPAL: memonitor progres pembangunan kapal di galangan melalui Laporan Harian & Laporan Mingguan (dengan lampiran foto/dokumen), Kurva-S (rencana vs realisasi progress), serta master data (perusahaan, data kapal, galangan, cuaca, kelembaban). Termasuk chat realtime, notifikasi, impersonate user, dan konfigurasi sistem.
- Dibangun di atas template "client-app" (SSO/RBAC boilerplate). README masih bernama Client App — itu normal, jangan "dibetulkan".
- Database boleh di-reset total. Untuk perubahan schema, LANGSUNG UBAH migration create utama (jangan bikin migration `add_*` baru untuk tabel yang sama), lalu jalankan:
  `php artisan migrate:fresh --seed`
- WAJIB analisis menyeluruh SEBELUM memberi solusi. Tidak ada duplicate logic, tidak ada field mati, tidak menghapus yang masih dipakai, dan hapus yang sudah tidak dipakai agar codebase CLEAN.

# Stack & Versi (JANGAN diganti)
- PHP ^8.5, Laravel ^13, Livewire ^4.1
- spatie/laravel-permission (role & permission)
- spatie/laravel-activitylog (audit log — terinstal, belum dipakai model; WAJIB dipakai untuk model penting baru)
- maatwebsite/excel ^4 (export/import Excel — di atas phpspreadsheet ^5; method concern WAJIB punya return type sesuai interface, mis. `collection(Collection $c): void` untuk import, `collection(): \Illuminate\Support\Collection` untuk export, `styles(Worksheet $s): ?array`, `query(): \Illuminate\Database\Eloquent\Builder`)
- barryvdh/laravel-dompdf (export PDF)
- phpoffice/phpword (export Word .docx — khusus Laporan Harian/Mingguan)
- laravel/reverb + laravel-echo (websocket/broadcast: chat & notifikasi)
- intervention/image (crop + konversi WebP untuk lampiran)
- Storage: disk `local` saja (FILESYSTEM_DISK=local). TIDAK ada S3/Flysystem.
- Frontend: Blade + TailwindCSS + Alpine (bawaan Livewire)
- Dev tools: laravel/pint (code style), phpunit ^12 (test — metadata pakai attribute PHP `#[Test]`/`#[DataProvider]`, BUKAN doc-comment `@test`)
- Bebas deprecation PHP 8.5 / Laravel 13 (WAJIB dijaga):
  - Konstanta PDO driver pakai kelas driver (`Pdo\Mysql::ATTR_SSL_CA`), bukan `PDO::MYSQL_ATTR_*`.
  - Jangan panggil `Reflection*::setAccessible()`, `curl_close()`, `finfo_close()`, `imagedestroy()`; jangan cast `(integer)/(boolean)/(double)/(binary)`; jangan pakai `null` sebagai array key; nullable WAJIB eksplisit `?Type $x = null`.
  - `Request::get()` deprecated -> pakai `$request->input()`/`query()`/`string()`.
  - Middleware CSRF bernama `PreventRequestForgery` (alias `VerifyCsrfToken`/`ValidateCsrfToken` deprecated).
  - `cache.serializable_classes=false` & `session.serialization=json` (hardening Laravel 13): simpan HANYA skalar/array di Cache & Session, JANGAN objek/model.
  - Cek runtime: `LOG_DEPRECATIONS_CHANNEL=single php artisan test` lalu pastikan tidak ada baris deprecation baru di `storage/logs/laravel.log`.

# Peta Domain (entity -> permission prefix)
- Master Data: `companies` (perusahaan), `data_kapal` (data kapal + `manage_kurva_s` + template upload/download), `galangan`, `kelembaban`, `cuaca`
- Laporan: `laporan` (dipakai bersama oleh Laporan Harian & Mingguan: `laporan_view`, `laporan_show`, `laporan_create`, `laporan_update`, `laporan_delete`, `laporan_download`, `laporan_lampiran_preview`, `laporan_lampiran_download`, `laporan_export_excel`, `laporan_export_pdf`, `laporan_view_all_data_kapal`)
- Settings: `users` (termasuk `users_impersonate`), `roles`, `configuration`, `manage_own_company`
- Lainnya: `notifications` (view, send), `chat` (view, create, delete), `dashboard_view`
- Model laporan: `LaporanHarian` + anak `LaporanAktivitas`, `LaporanPersonel`, `LaporanPeralatan`, `LaporanConsumable`, `LaporanLampiran`, `LaporanExternal`; `LaporanMingguan` + `LaporanMingguanProgress`; Kurva-S: `KurvaSWorkGroup`, `KurvaSRencana`

# Arsitektur Wajib (ikuti pola yang ada, JANGAN bikin pola baru)
1. LIVEWIRE-FIRST. Semua fitur = Livewire Component (`App\Livewire\...`). Controller HANYA untuk auth/callback SSO.
   - Struktur: `App\Livewire\MasterData\{Entity}Management` (index), `App\Livewire\{Modul}\{Modul}Index|Create|Edit|Show` (halaman penuh).
2. SERVICE LAYER. Semua business logic & query di `App\Services\{Entity}Service`. Component TIDAK query langsung untuk operasi tulis (delegasikan ke Service).
3. POLICY per model di `App\Policies` + registrasi Gate. SEMUA aksi lewat `$this->authorize(...)` di component DAN `->middleware('can:...')` di route.
4. ENUM untuk status/opsi di `App\Enums` dengan method `label()` (dan `color()`/`badgeClass()` bila untuk badge). DILARANG hardcode warna/label status di Blade.
5. TRAIT yang WAJIB dipakai ulang (jangan bikin duplikat):
   - `App\Livewire\Traits\HasNotification` -> notifySuccess/notifyError/notifyWarning/notifyInfo/notifyValidationError
   - `App\Livewire\Traits\HasMenuItems` -> daftarkan menu baru DI SINI dengan Gate check (jangan hardcode di sidebar)
   - `App\Livewire\Traits\HasDataKapalFilter` -> filter data laporan per data kapal (scope akses user)
   - `App\Traits\HasDynamicLike` -> operator LIKE lintas DB (sqlite/mysql)
   - `App\Traits\HasEncryptedRouteKey` -> route-model-binding ID terenkripsi (cegah ID enumeration/IDOR). Saat ini dipakai `LaporanHarian` & `LaporanMingguan`.
   - `App\Traits\HasUserStamps` -> auto-fill `created_by`/`updated_by` (relasi `creator()`/`updater()`); model bisa batasi kolom via `protected array $userStamps`.
   - `App\Traits\SortsQuery` -> `applySorting()` di Service: whitelist field sortable (key=nama field UI, value=kolom/ekspresi SQL), NULL terkecil, tie-break `id`.
   - `App\Livewire\Traits\HasTableSorting` -> properti `$sortField`/`$sortDir` + `sortBy()` (toggle asc/desc + `resetPage()`) untuk semua tabel index.
   - `App\Services\QueueStatusService` -> `markWorkerActive()` di awal `handle()` Job untuk status worker.

# Permission-First (ikuti pola yang ada)
- Single source of truth: `Database\Seeders\PermissionSeeder`, konvensi nama `{entity}_{action}`
  (action umum: view, show, create, update, delete, export_excel, export_pdf; aksi khusus: impersonate, send, download, upload_template, download_template, manage_kurva_s, lampiran_preview, lampiran_download, view_all_data_kapal).
- Route WAJIB diberi middleware permission: `Route::get('/x', ...)->middleware('can:{entity}_view')`.
- Grouping UI: tambahkan mapping di `App\Services\RolePermissionService::buildPermissionGroups()`.
- Di Blade gunakan `@can('permission')`, JANGAN hardcode role.
- Setiap fitur baru WAJIB punya: permission (seeder) + Policy + Gate check di HasMenuItems + middleware `can:` di route.

# Routing & Halaman (2 pola yang ADA, ikuti sesuai konteks)
1. INDEX/halaman sederhana (master data, settings): route closure mengembalikan PAGE VIEW yang membungkus component.
   ```php
   Route::get('/cuaca', function () {
       return view('master-data.cuaca');
   })->middleware('can:cuaca_view')->name('cuaca');
   ```
   Page view (`resources/views/master-data/cuaca.blade.php`):
   ```blade
   <x-app-layout title="Master Data - Cuaca">
       <x-slot name="header"><h2 ...>{{ __('Master Data - Cuaca') }}</h2></x-slot>
       <livewire:master-data.cuaca-management />
   </x-app-layout>
   ```
2. FULL-PAGE FORM/CRUD kompleks (laporan): route langsung ke Livewire component class, satu route per aksi.
   ```php
   Route::prefix('laporan-harian')->name('laporan-harian.')->middleware('can:laporan_view')->group(function () {
       Route::get('/', LaporanHarianIndex::class)->name('index');
       Route::get('/create', LaporanHarianCreate::class)->middleware('can:laporan_create')->name('create');
       Route::get('/{laporanHarian}', LaporanHarianShow::class)->middleware('can:laporan_show')->name('show');
       Route::get('/{laporanHarian}/edit', LaporanHarianEdit::class)->middleware('can:laporan_update')->name('edit');
   });
   ```

# Form Strategy: Modal vs Full-Page (WAJIB baca sebelum buat form)
Gunakan FORM MODAL hanya untuk form SEDERHANA (1-3 field, tidak ada nested/repeater, tidak ada upload file).
WAJIB gunakan FULL-PAGE FORM (Livewire component class terpisah) jika MEMENUHI salah satu:
- Form punya > 3 field atau ada section/grup kolom.
- Ada nested data / repeater (mis. personel, peralatan, consumable, aktivitas pada laporan).
- Ada upload file dengan status processing (mis. lampiran laporan).
- Ada relasi many-to-many yang dipilih dari form.

Pola full-page form:
1. Route `/{entity}/create` -> `{Entity}Create` component class; `/{entity}/{model}/edit` -> `{Entity}Edit` (route-model-binding + `can:` middleware).
2. Livewire Form Component: `mount($model = null)`, set `$editMode`, load nested data bila edit.
3. Tombol Save -> `redirect(route(...), navigate: true)` kembali ke index. Tombol Cancel -> sama.
4. Index (list) tetap pakai modal HANYA untuk delete confirmation (`x-delete-modal`/`x-delete-confirmation-modal`).

## Layout Full-Page Form (WAJIB ikuti pola yang ada, JANGAN deviasi)
- Root container: `<div class="w-full">` — JANGAN pakai `max-w-*` atau `mx-auto` (form full-width).
- Main card: `bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700`.
- Grid form: `grid grid-cols-1 md:grid-cols-2 gap-4`; field full-width tambahkan `md:col-span-2`.
- Section separator: `border-t border-gray-200 dark:border-gray-700` + heading `text-lg font-semibold ... pb-2 mb-4`.
- Action bar = CARD TERPISAH di luar main card: `flex flex-col sm:flex-row items-center justify-between gap-3 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 px-5 py-4`
  dengan `<x-cancel-button>` kiri + `<x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan...">` kanan (pola `laporan-harian-create`).
- Breadcrumb WAJIB di header halaman (mis. Laporan Harian > Edit).

# UI/UX Konsisten (pakai reusable components yang SUDAH ADA)

## Inventaris Komponen (CEK INI DULU sebelum buat komponen baru)
| Kategori | Komponen | Path |
|----------|----------|------|
| Tombol | `<x-loading-button>` | `components/loading-button.blade.php` (variant filled + `<x-slot:icon>` custom SVG) |
| Tombol | `<x-cancel-button>` | `components/cancel-button.blade.php` |
| Tombol | `<x-primary-button>` | `components/primary-button.blade.php` (legacy, no loading) |
| Tombol | `<x-secondary-button>` | `components/secondary-button.blade.php` (legacy, no loading) |
| Tombol | `<x-danger-button>` | `components/danger-button.blade.php` (legacy, no loading) |
| Modal | `<x-modal>` | `components/modal.blade.php` |
| Modal | `<x-delete-modal>` / `<x-delete-confirmation-modal>` | `components/delete-modal.blade.php`, `delete-confirmation-modal.blade.php` |
| Modal | `<x-confirm-modal>` / `<x-document-confirm-modal>` | `components/confirm-modal.blade.php`, `document-confirm-modal.blade.php` |
| Modal | `<x-image-crop-modal>` | `components/image-crop-modal.blade.php` (crop foto lampiran) |
| Modal | `<x-lampiran-preview-modal>` | `components/lampiran-preview-modal.blade.php` (preview lampiran) |
| Modal | `<x-upload-template-modal>` / `<x-download-template-modal>` | import/template Excel (dipakai data-kapal/kurva-S) |
| Modal | `<x-reset-password-modal>` | `components/reset-password-modal.blade.php` |
| Form | `<x-input-label>` (prop `:required`) | `components/input-label.blade.php` |
| Form | `<x-text-input>` | `components/text-input.blade.php` |
| Form | `<x-input-error>` | `components/input-error.blade.php` |
| Select | `<x-searchable-select>` | `components/searchable-select.blade.php` |
| Select | `<x-multi-searchable-select>` | `components/multi-searchable-select.blade.php` |
| Filter | `<x-filter-popover>` | `components/filter-popover.blade.php` |
| Tabel | `<x-sortable-header>` | `components/sortable-header.blade.php` (th sortable: `field`/`sortField`/`sortDir`/`compact`) |
| Notif | `<x-toast>` | `components/toast.blade.php` (auto-render di layout) |
| Notif | `<x-action-message>` | `components/action-message.blade.php` |
| Icon | `<x-icon>` | `components/icon.blade.php` |
| Toggle | `<x-toggle-switch>` | `components/toggle-switch.blade.php` (status aktif/nonaktif) |
| Kurva-S | `<x-kurva-s-chart>`, `<x-kurva-s-chart-card>`, `<x-kurva-s-actions>`, `<x-kurva-s-import-modal>` | `components/kurva-s-*.blade.php` |
| Domain | `components/laporan/`, `components/laporan-mingguan/` | partials form & section laporan — pakai ulang, jangan duplikat |
| Layout | `<x-app-layout>` | `layouts/app.blade.php` (prop `title`, slot `header`) |
| Layout | `<x-guest-layout>` | `layouts/guest.blade.php` |

DILARANG buat komponen baru jika fungsi sudah ada di tabel di atas.
Jika butuh variant baru (mis. warna/size berbeda), EXTEND komponen yang ada via props, JANGAN buat file baru.

## Aturan Pakai Komponen
- `<x-loading-button>` (WAJIB untuk tombol filled/header action):
  - Props: `variant` (`primary`, `success`, `danger`, `warning`, `secondary`), `size` (`xs`/`sm`/`md`/`lg`), `target` (wire:target), `loadingText`, `title`.
  - Icon: SELALU via `<x-slot:icon>` berisi inline SVG Heroicons (`w-4 h-4` untuk filled, `w-5 h-5` untuk tombol besar). TIDAK ada prop `icon` built-in di app ini.
  - Contoh: `<x-loading-button wire:click="exportExcel" target="exportExcel" variant="success" size="md" loadingText="Exporting..." title="Export Excel"><x-slot:icon><svg .../></x-slot:icon>Excel</x-loading-button>`.
  - Tombol navigasi (Tambah/View/Edit halaman) -> `wire:click` + redirect method di component: `return $this->redirect(route('...', $model), navigate: true);`. DILARANG raw `<a href>` ber-styling button (kecuali breadcrumb/text link).
  - `<x-loading-button>` di dalam SEL tabel dengan `loadingText` WAJIB fixed width (mis. `class="w-24"`): swap teks->spinner mengubah lebar tombol; pada tabel `whitespace-nowrap` + `overflow-x-auto` itu memunculkan scrollbar horizontal (height card "melompat").
- Tombol ICON di dalam tabel (edit/delete/view) memakai pola raw `<button>` yang sudah ada (belum ada komponen icon-button — ikuti pola ini, jangan buat komponen baru tanpa kebutuhan):
  ```blade
  <button wire:click="edit({{ $item->id }})"
      wire:loading.attr="disabled"
      wire:target="edit({{ $item->id }})"
      class="text-purple-600 hover:text-purple-900 dark:text-purple-400 dark:hover:text-purple-300 disabled:opacity-50"
      title="Edit">
      <svg wire:loading.class="hidden" wire:target="edit({{ $item->id }})" class="w-5 h-5" ...>...</svg>
      <svg wire:loading wire:target="edit({{ $item->id }})" class="animate-spin w-5 h-5" ...>...</svg>
  </button>
  ```
  Konvensi warna: edit=`text-purple-600`/`text-blue-600` (ikuti halaman sejenis), delete=`text-red-600`, view=`text-blue-600`/`text-gray-600`.
- TOGGLE status (aktif/nonaktif): pakai `<x-toggle-switch>` (`components/toggle-switch.blade.php`). Contoh: `settings/user-management.blade.php`.
- Setiap action button di dalam loop tabel WAJIB `wire:target="action({{ $item->id }})"` + `wire:loading.attr="disabled"`, dan parent row WAJIB `wire:key="row-{{ $item->id }}"` agar Livewire DOM-diff tidak salah mencocokkan elemen setelah delete/reorder.
- Batal: `<x-cancel-button>`. Hapus: `<x-delete-modal>`/`<x-delete-confirmation-modal>`. Konfirmasi: `<x-confirm-modal>`.
- Select: `<x-searchable-select>` / `<x-multi-searchable-select>`. Filter: `<x-filter-popover>`.
- Form field: `<x-input-label>`, `<x-text-input>`, `<x-input-error>`.
  - WAJIB beri `placeholder` deskriptif Bahasa Indonesia di SETIAP `<x-text-input>` (kecuali `type="date"`/`hidden`).
  - Field required (di `rules()`) WAJIB `:required="true"` di `<x-input-label>` agar muncul `*` merah.
- Upload lampiran foto: tawarkan crop via `<x-image-crop-modal>` bila relevan; status file via ikon inline (pola `laporan-mingguan/external-reports-section`: spinner=processing, ikon merah=failed, hijau=completed); preview via `<x-lampiran-preview-modal>`.
- WAJIB dukung dark mode (kelas `dark:...`), spacing/typography konsisten dengan menu sejenis.
- String UI berbahasa Indonesia (boleh hardcode, app belum pakai lang files).
- Breadcrumb WAJIB di full-page form (mis. Laporan Harian > Edit).
- DILARANG menulis raw `<button wire:click>` dengan SVG spinner manual untuk tombol FILLED — pakai `<x-loading-button>`. (Pola raw button hanya untuk icon action di tabel & toggle.)

## Toolbar Index & Filter (pola WAJIB untuk semua halaman index)
Urutan toolbar: `[search flex-1] → [x-filter-popover] → [action buttons]`.
- SEMUA filter data (`*Filter` props) + select `perPage` WAJIB masuk ke dalam `<x-filter-popover>` — JANGAN taruh inline di toolbar.
  ```blade
  <x-filter-popover :filters="['companyFilter', 'statusFilter']" :per-page="true">
      <div>
          <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Perusahaan</label>
          <x-searchable-select wire:model.live="companyFilter" :options="..." placeholder="Semua Perusahaan" searchPlaceholder="Cari perusahaan..." />
      </div>
      {{-- filter lain, masing-masing dibungkus <div> + <label> kecil --}}
  </x-filter-popover>
  ```
- Kontrak `x-filter-popover` (WAJIB dipenuhi component):
  1. Prop `:filters` = array nama wire property — dipakai untuk badge jumlah filter aktif di tombol. JANGAN masukkan `perPage`/`search` ke sini.
  2. Prop `:per-page="true"` = render select "Tampilan per Halaman" (10/25/50/100, `wire:model.live="perPage"`, clearable=false) otomatis di bawah slot — pakai di semua index yang punya `perPage`. Halaman tanpa filter data boleh pakai `<x-filter-popover :per-page="true" />` tanpa slot.
  3. Method `resetFilters()` HARUS ada (tombol "Reset Filter" di-wired internal): `public function resetFilters() { $this->reset(['search', ...$filterProps, 'perPage']); $this->resetPage(); }` — ikut reset `search` dan `perPage` (bila `per-page` dipakai).
  4. Setiap filter prop punya `updating{Prop}()` -> `$this->resetPage()` (termasuk `updatingPerPage`).
  5. Tiap select di dalam popover dibungkus `<div>` + `<label class="block text-xs font-medium ...">` dan placeholder `"Semua {Nama}"` (bukan "Filter {Nama}").
- `search` TETAP inline di toolbar (bukan di popover).
- Pengecualian: filter 2-3 opsi mutually-exclusive yang sering diganti cepat (mis. Semua/Belum dibaca/Sudah dibaca di notifikasi) boleh pakai pill-tabs, bukan popover.
- Filter select di dalam FORM/modal TIDAK masuk popover — aturan ini hanya untuk toolbar index.

## Toast Hasil Filter/Search (pola `filterChanged`)
Setiap perubahan search/filter menampilkan toast jumlah hasil — pola sudah diterapkan di `UserManagement`, `SystemConfiguration`, `ImpersonateUser`. WAJIB diterapkan saat membuat/menyentuh halaman list:

1. Properti: `public bool $filterChanged = false;`
2. Set flag di SEMUA hook `updating*` (search + setiap filter) dan `resetFilters()`:
   ```php
   public function updatingSearch()
   {
       $this->resetPage();
       $this->filterChanged = true;
   }
   ```
3. Konsumsi flag di `render()` SETELAH query paginator (pakai `->total()`, akurat lintas halaman):
   ```php
   if ($this->filterChanged) {
       $this->notifyInfo("Ditemukan {$items->total()} data {entity}.");
       $this->filterChanged = false;
   }
   ```
- JANGAN panggil notify di hook `updating*` — hasil belum dihitung saat itu; selalu di `render()` setelah query.
- Bila halaman punya konteks tambahan (mis. data kapal terpilih), ekstrak penyusunan pesan ke method di component — jangan tulis logic pesan di Blade.

## Livewire & Blade Gotchas (pelajaran dari bug fix — WAJIB dihindari)
1. **`wire:loading.class` syntax**: pakai `wire:loading.class="hidden"` (tambah class saat loading) dan `wire:loading.class.remove="..."`. Jangan andalkan modifier `.add` — pola terbukti di tabel memakai `wire:loading.class="hidden"` pada SVG ikon.
2. **PHP string interpolation TIDAK mendukung expression**:
   - SALAH: `"class=\"{$isDisabled ? 'opacity-50' : ''}\""` -> ParseError.
   - BENAR: ekstrak ke variabel di `@php` dulu: `$disabledClass = $isDisabled ? 'opacity-50' : '';`.
3. **`wire:loading` + `display: flex` conflict**: `wire:loading` men-set inline `display: ''` saat shown yang meng-override class `flex` -> konten tidak ter-center. Taruh `wire:loading` langsung pada elemen konten (SVG), jangan pada wrapper span flex.
4. **Component slot vs prop conflict**: deteksi slot dengan `$icon instanceof \Illuminate\View\ComponentSlot && !$icon->isEmpty()`. Variabel `$iconSlot` TIDAK ADA — jangan `isset($iconSlot)`.
5. **`wire:key` WAJIB untuk semua row/action di loop**: `wire:key="row-{id}"` pada `<tr>`/wrapper. Tanpa itu, delete/reorder membuat Livewire DOM-diff mencocokkan elemen yang salah (spinner muter di button lain).
6. **Stale input antar-entitas pada nested/tab form (morph carryover)**: `wire:model` men-set nilai client-side; saat node dipakai ulang untuk entitas lain, nilai DOM lama "bocor". SOLUSI: `row_key` stabil per baris (`uuid` untuk baru, `'{prefix}-{id}'` untuk persisted) di `wire:key` semua wrapper; untuk layout tab bungkus panel dengan `wire:key="panel-{row_key}"`.
7. **Stale `wire:model` saat baris di-REORDER**: di Livewire 4 `wire:model` meng-capture expression (mis. `items.0.name`) saat init — tidak dievaluasi ulang saat node pindah posisi. SOLUSI: sertakan index posisi di `wire:key` (`item-{row_key}-{index}`) agar node di-render ulang, ATAU pakai `value="..."` + `wire:blur="updateX(..., $event.target.value)"`.
8. **Directive Blade di dalam komentar JS/HTML tetap DI-PARSE**: menulis teks `@if(...)`/`@foreach(...)` di komentar `//` atau `<!-- -->` dalam file `.blade.php` membuat counter directive tak seimbang -> ParseError "unexpected end of file, expecting endif". Jangan tulis nama directive di komentar (pakai `{{-- --}}` pun hindari menyebut directive ber-kurung).
9. **Scroll lock modal yang dibungkus `@if` server-side**: elemen dihapus Livewire morph TANPA memicu `$watch`, sehingga `document.body.style.overflow='hidden'` tertinggal (scroll mati sampai hard refresh). Selalu `return () => { document.body.style.overflow = '' }` dari `x-init` sebagai cleanup (contoh: `x-document-confirm-modal`).
10. **Options `x-searchable-select`/`x-multi-searchable-select` bisa STALE — SUDAH di-fix, JANGAN regresi**:
    - `options: @js($options)` di `x-data` hanya dibaca SEKALI saat Alpine init; root `wire:ignore.self` membuat node tidak di-render ulang -> opsi baru server-side tidak pernah masuk dropdown.
    - FIX yang ADA di komponen: opsi juga di-render ke `<div x-ref="optionsData" class="hidden">{!! json_encode($options, JSON_HEX_*) !!}</div>` — child TETAP ikut morph tiap render (`.self` hanya mengabaikan root). Saat dropdown dibuka, `refreshOptions()` membaca ulang elemen itu -> selalu fresh.
    - `componentId` deterministik: `md5($wireModel.'|'.$placeholder)` — JANGAN `uniqid()` (berubah tiap render -> node ganda saat morph).
    - JANGAN andalkan `wire:key` signature di root `wire:ignore.self` untuk refresh opsi — key berubah = node beda, risiko node ganda saat morph.

# File Storage & Lampiran (pola WAJIB — async via queue)
Alur yang ADA (storage `local`, TANPA S3/FileStorageService):
1. Di Livewire Component: `$tempPath = $file->store('temp/{fitur}', 'local')`, simpan record dengan `file_status='pending'/'processing'`, lalu `ProcessXxx::dispatch($model, $tempPath, $cropData)`.
2. Di Job (`implements ShouldQueue`, `$tries=3`, ada `failed()`):
   - Panggil `$queueStatusService->markWorkerActive()` di awal `handle()`.
   - Path final: `'{fitur}/{sub}/' . time() . '_' . uniqid() . '.' . $ext` (contoh: `'laporan-lampiran/harian/' . $fileName`).
   - Gambar: proses via intervention/image — apply crop (jika ada) lalu `->toWebp(85)` (WAJIB WebP agar kompatibel PhpWord saat generate dokumen).
   - `Storage::disk('local')->put($path, $content)`, update `file_path/file_name/file_size/file_status='completed'/file_processed_at`, hapus temp.
   - Gagal: `file_status='failed'` + `file_error`, cleanup temp di `failed()`.
3. Download/preview/delete: SELALU via `Storage::disk('local')`.
4. Validasi upload: `config/file_upload.php` + helper `file_upload_validation_rule()`, `get_max_upload_size()`, `get_allowed_mimes()`. Jangan percaya nama file dari client.
5. Kolom status konvensi: `file_status` (pending/processing/completed/failed), `file_error`, `file_processed_at` — model punya helper `isProcessing()/isCompleted()/isFailed()` (lihat `LaporanExternal`).

# Export Word (.docx) — Khusus Laporan
- phpoffice/phpword via `LaporanHarianWordService` / `LaporanMingguanWordService`; gambar disisipkan lewat `WordImageService`.
- Generate dokumen ASYNC via `GenerateLaporanHarianJob` / `GenerateLaporanMingguanJob` (queue), status file di model laporan (file_status) + notifikasi ke user saat selesai.
- Export Excel (maatwebsite) di `App\Exports`, PDF (dompdf) di `resources/views/exports/*-pdf.blade.php`, import Excel di `App\Imports`.
- Setiap aksi export/download WAJIB permission-nya sendiri (`*_export_excel`, `*_export_pdf`, `laporan_download`).

# Protokol Kerja AI (WAJIB DIIKUTI URUT)
FASE 1 - ANALISIS (tampilkan dulu, sebelum menulis kode):
- Ringkas dampak: file/model/migration/permission/menu apa yang tersentuh.
- Cek duplikasi: apakah sudah ada Service/Trait/Component/Enum serupa? Pakai ulang, jangan bikin baru.
- Cek relasi & efek samping (cascade delete, pivot, file terkait, relasi laporan anak).
- Rencana permission + Policy + grouping + menu + middleware route.

FASE 2 - RENCANA: daftar langkah bernomor + daftar file yang akan dibuat/diubah.

FASE 3 - EKSEKUSI: implementasi sesuai pola yang ada.

FASE 4 - VERIFIKASI: jalankan/ajukan `php artisan migrate:fresh --seed`, cek tidak ada error, dan lampirkan CHECKLIST "Definition of Done".

Jika ada ambiguitas yang berdampak besar -> TANYA dulu, jangan berasumsi.

# Audit Log (spatie/laravel-activitylog)
- Package terinstal; WAJIB dipakai untuk model penting BARU (dan saat refactor model lama): trait `LogsActivity` + `getActivitylogOptions()` (logOnly kolom relevan, `logOnlyDirty()`, `dontSubmitEmptyLogs()`).
- Beri `useLogName('{entity}')` konsisten agar mudah difilter.
- Aksi sensitif (delete, toggle status, impersonate, kirim notifikasi) HARUS terekam.

# Data Integrity & Transaksi
- Operasi multi-tabel (laporan + anak: aktivitas/personel/peralatan/consumable/lampiran) WAJIB `DB::transaction()`.
- Cek dependency sebelum delete (mis. perusahaan dengan users, data kapal dengan kurva-S) dan beri pesan jelas via HasNotification.
- Gunakan `findOrFail` + authorize di SETIAP aksi berbasis id.
- Normalisasi input konsisten (mis. `strtoupper` untuk kode) di Service, bukan di Component.

# Performa & Scalability
- WAJIB eager loading (`with`/`withCount`) untuk cegah N+1. Dilarang query di dalam loop Blade.
- Selalu paginate list (default 10-15/hal), jangan `->get()` untuk data tabel.
- Cache per-request untuk data yang dipakai berulang (pola `static $cache` seperti HasMenuItems).
- Filter/search server-side via Service + HasDynamicLike, gunakan `wire:model.live.debounce.300ms`.
- Query berat/eksport besar -> queue/chunk (pola GenerateLaporan*Job).

# Real-Time (laravel/reverb + Echo)
- Echo client sudah dikonfigurasi di `resources/js/echo.js` (broadcaster: reverb).
- Event broadcast: `App\Events\NewNotification`, `NewChatMessage`, `UserTyping` — kirim dengan `broadcast(new Xxx(...))->toOthers()` bila perlu.
- Kanal privat di-authorize di `routes/channels.php` (`user.{id}`, `chat.{chatId}`). Setiap kanal baru WAJIB authorize.
- Jangan polling jika bisa broadcast.

## Menu Badge Realtime (pola Chat — sudah diterapkan)
- Item menu boleh punya key `'badge'` (angka unread) di `HasMenuItems::getMenuItems()`: sidebar menampilkan pill (cap `99+`) & navbar menampilkan pill yang sama. Render badge sudah ada di `sidebar.blade.php`/`navigation.blade.php` — cukup ISI key-nya.
- Hitung via 1 query, BUKAN loop: contoh `Chat::totalUnreadCountForUser($user->id)` (hormati `last_read_at` & `deleted_at` pivot per-user).
- Listener realtime ada di `HasMenuItems::getListeners()` (diwarisi Sidebar & Navigation): `echo-private:user.{id},NewChatMessage` + event lokal `chat-unread-changed` -> handler `refreshUnreadChatBadge()` (no-op yang memicu re-render; cache `getMenuItems()` per-request).
- Setiap titik "dibaca" WAJIB dispatch `chat-unread-changed` agar badge turun tanpa reload (contoh: `ChatIndex` saat pesan dibaca/chat dihapus).
- Menu lain yang butuh badge realtime: isi `'badge'` di item menu + tambahkan listener broadcast/event Livewire yang relevan via `getListeners()` trait yang sama — JANGAN bikin mekanisme badge baru.

# UX Detail (wajib, bukan opsional)
- Setiap tabel: empty state (ikon + pesan), loading state, dan pagination.
- Setiap form: validasi realtime (`rules()` + `validationAttributes()` Bahasa Indonesia), disable tombol saat proses.
- Aksi destruktif: SELALU pakai modal konfirmasi (`x-delete-modal`/`x-delete-confirmation-modal`/`x-confirm-modal`).
- Feedback: SELALU notifikasi hasil (sukses/gagal) via HasNotification.
- Upload file: tampilkan status file (pending/processing/completed/failed) via ikon status inline + tombol download/preview bila selesai.
- Responsive: uji layout mobile (stack) & desktop.

# Design System & Responsive (WAJIB, bukan opsional)

## Prinsip Desain
- KONSISTENSI adalah prioritas #1. Ikuti pola visual yang sudah ada di app.
- Content padding: `px-4 sm:px-6 lg:px-8` (sudah ada di layout).
- Card/panel: `bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700`.
- Page header: judul `text-2xl font-bold text-gray-900 dark:text-white` + subjudul `text-sm text-gray-500 dark:text-gray-400`.
- Section header di form: `text-lg font-semibold ... border-b ... pb-2 mb-4`.
- Table header: `bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold uppercase tracking-wider`.
- Table row: `hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors`.
- Badge status: pakai method `badgeClass()` dari Enum, JANGAN hardcode warna di Blade.

## Field Height Consistency (WAJIB)
`<x-text-input>`, `<x-searchable-select>`, dan `<x-multi-searchable-select>` WAJIB punya tinggi & style yang sama saat berdampingan di form.
- `<x-text-input>` component sudah include `border text-sm px-3 py-2.5 rounded-lg` — JANGAN override padding/border dari usage (`class="mt-1 block w-full"` saja).
- `<x-searchable-select>` & `<x-multi-searchable-select>` trigger pakai `border text-sm py-2.5 pl-4 pr-10 rounded-lg`.
- **PENTING:** `border` class WAJIB (untuk border-width 1px). `border-gray-300` saja hanya set warna → border tidak tampil.
- Textarea WAJIB pakai `border px-3 py-2 text-sm rounded-lg` (jangan tanpa padding/border).
- JANGAN pakai `rounded-md`/`rounded-xl` atau `focus:ring-indigo-*` di form — WAJIB `rounded-lg` dan `focus:ring-blue-500` untuk konsistensi.

## Dark Mode Color Palette (WAJIB konsisten)
Semua input field di dark mode WAJIB pakai `dark:bg-gray-700` (abu-abu), BUKAN `dark:bg-gray-900` (hitam) — konsisten dengan searchable-select & textarea.

| Element | Light | Dark | Catatan |
|---------|-------|------|---------|
| Input text (`<x-text-input>`) | `bg-white` | `dark:bg-gray-700` | `rounded-lg`, `text-sm`, `px-3 py-2.5` |
| Textarea (raw) | `bg-white` | `dark:bg-gray-700` | Border: `dark:border-gray-600` |
| Searchable select trigger | `bg-white` | `dark:bg-gray-700` | Border: `dark:border-gray-600` |
| Card/panel | `bg-white` | `dark:bg-gray-800` | |
| Table header | `bg-gray-50` | `dark:bg-gray-700/50` | |
| Page background | `bg-gray-100` | `dark:bg-gray-900` | Hanya background utama, BUKAN input |
| Border | `border-gray-200` | `dark:border-gray-700` | Card/section |
| Border input | `border-gray-300` | `dark:border-gray-600` | Input field |
| Text primary | `text-gray-900` | `dark:text-white` | |
| Text secondary | `text-gray-500` | `dark:text-gray-400` | |
| Focus ring | `focus:ring-blue-500` | `dark:focus:ring-blue-400` | |

## Missing Computed Property (WAJIB dicek sebelum selesai)
Setiap `$this->xxxOptions` yang dipanggil di Blade (biasanya untuk `<x-searchable-select :options="$this->xxxOptions">`)
WAJIB punya method `getXxxOptionsProperty()` yang match di Livewire component-nya. Menambah field baru di Blade tapi lupa
menambah computed property-nya menyebabkan `Livewire\Exceptions\PropertyNotFoundException` RUNTIME (baru ketahuan saat
modal dibuka). Sebelum task selesai, WAJIB grep `this->\w+Options` di Blade yang diubah dan cross-check ke
`function get\w+OptionsProperty` di component terkait.

## Policy untuk Aksi Transisi Status (WAJIB konsisten dgn permission di Blade)
Untuk aksi yang mengubah status entity (approve/reject/submit/dll), method Policy WAJIB:
1. Cek permission YANG SAMA dengan yang dipakai `@can(...)` di Blade untuk menampilkan tombolnya.
2. Cek status saat ini valid untuk transisi tersebut.
JANGAN pakai `$this->authorize('update', $model)` (permission generic) untuk aksi transisi khusus — tombol yang
terlihat di UI (di-gate permission spesifik) akan gagal saat diklik, atau sebaliknya membuka celah user dengan
`update` saja bisa memicu transisi yang seharusnya butuh permission khusus.

## Responsive WAJIB (cek di setiap halaman)
- Mobile-first: class mobile dulu, lalu `sm:`, `md:`, `lg:`.
- Tabel: `overflow-x-auto` wrapper di luar `<table>`.
- Grid form: `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4`.
- Action bar: tombol `w-full sm:w-auto`; header action bar pakai `flex-col lg:flex-row` (pola index laporan/master-data).
- Filter & search: stack di mobile (`flex flex-col sm:flex-row gap-3`).
- Modal: `max-w-2xl` default, content `px-4 py-4 sm:p-6`.
- Sidebar: auto-collapse via Alpine store (jangan override).
- Pagination: `hidden sm:flex` untuk prev/next text, ikon saja di mobile.
- Empty state: `py-12 text-center`, ikon `h-12 w-12 mx-auto text-gray-400`.
- Font size: `text-sm` tabel/form, `text-base` page header, `text-xs` meta/badge.

## Aksesibilitas Dasar
- Setiap input WAJIB `<x-input-label>` dengan `for`.
- Tombol icon-only WAJIB `title`/`aria-label`.
- Focus ring: `focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800`.
- `x-cloak` pada elemen Alpine dengan `x-show`.

# Error Handling (pola seragam)
Bungkus aksi Service call dengan try/catch:
- catch AuthorizationException -> notifyError("Anda tidak memiliki izin...")
- catch ValidationException -> notifyValidationError($e) lalu rethrow
- catch \Exception -> log error + notifyError("Terjadi kesalahan sistem. Silakan coba lagi.")

JANGAN bocorkan pesan exception mentah ke user.

# Testing & Quality Gate
- Sertakan minimal Feature test (PHPUnit) untuk: authorize gagal/berhasil, CRUD, dan permission gate.
- Jalankan `./vendor/bin/pint` (code style) sebelum selesai.
- Factory & Seeder idempotent; daftarkan seeder baru di DatabaseSeeder dengan urutan benar (Permission -> Role -> User -> data master -> data laporan/contoh).

# Security-Aware (WAJIB, bukan opsional)
- Semua endpoint/aksi ter-authorize: `can:` middleware di route + `$this->authorize()`/Policy di component.
- Validasi & sanitasi semua input; mass-assignment aman (`$fillable` eksplisit).
- File: validasi mime & size via `config/file_upload.php`; jangan percaya nama file dari client.
- Jangan hardcode secret; pakai .env. Hormati fitur SSO/impersonate yang sudah ada.
- Scope data laporan per data kapal pakai `HasDataKapalFilter` + permission `laporan_view_all_data_kapal` — jangan bypass.

# Integrasi SSO (kontrak dengan sso-server — JANGAN ubah sepihak)
- Login: OAuth2 Authorization Code ke sso-server (`config('services.sso.*')`, aktif bila `IS_USING_SSO=true`); callback di `App\Http\Controllers\Auth\SsoAuthController` (validasi `state` di session).
- Endpoint sinkronisasi dari sso-server di `routes/api.php` prefix `/sso` (`ping`, `users`, `users/sync`, `users/remove`, `users/sync-roles`, `roles`, `roles/sync`, `roles/delete`, `permissions`) via `SsoSyncController`, dilindungi `throttle:60,1` + middleware `sso.secret` (`VerifySsoSecret`: header `X-SSO-Secret`, dibandingkan `hash_equals` terhadap `SSO_API_SECRET`).
- Mengubah path/payload endpoint ini WAJIB diikuti perubahan di sisi sso-server (dan sebaliknya).

# Encrypted Route Model Binding (WAJIB untuk semua model yang tampil di URL)
- Model di route parameter WAJIB trait `App\Traits\HasEncryptedRouteKey` (sudah dipakai `LaporanHarian` & `LaporanMingguan`).
  Tujuan: ID di URL berupa ciphertext, tidak bisa ditebak/di-enumerate (cegah IDOR).
- Route tetap normal: `Route::get('/{laporanHarian}', LaporanHarianShow::class)`.
- Saat menerima ID di method Livewire (mis. `edit($id)` dari tombol): DEKRIPSI dengan `Crypt::decryptString($id)` atau pakai route-model-binding — JANGAN pakai ID mentah.
- Enkripsi ID BUKAN pengganti otorisasi — tetap WAJIB `$this->authorize(...)`.
- Jangan apply ke model yang TIDAK muncul di URL (pivot, log, model anak laporan).

# Definition of Done (checklist WAJIB di akhir jawaban)
- [ ] Migration diubah di file create utama + `migrate:fresh --seed` sukses
- [ ] Model: relasi, casts, $fillable, Enum, LogsActivity (model penting baru), HasEncryptedRouteKey (jika muncul di URL)
- [ ] Service: business logic + transaksi + normalisasi input
- [ ] Livewire: rules()+attributes, authorize di tiap aksi, loading state (wire:target + disabled), notifikasi
- [ ] Form strategy tepat: modal untuk sederhana, full-page component class untuk kompleks (nested/repeater/upload)
- [ ] Permission di PermissionSeeder + `can:` middleware di route + Policy terdaftar + grup di RolePermissionService + menu di HasMenuItems
- [ ] Blade: pakai reusable components (cek inventaris), dark mode, TANPA logic (logic di Enum/Service)
- [ ] Filter index + `perPage` di dalam `<x-filter-popover>` (`:filters` + `:per-page` + `resetFilters()` + `updating*` resetPage); `search` tetap inline
- [ ] Responsive: mobile-first, tabel overflow-x-auto, grid form adaptif, action bar stack di mobile
- [ ] Row & action button di loop punya wire:key/wire:target unik + loading state
- [ ] File (jika ada): async via Job + status file + cleanup temp + markWorkerActive
- [ ] Lampiran gambar (jika ada): crop + WebP q85 agar kompatibel export Word
- [ ] Export Excel/PDF/Word (jika relevan) + permission-nya
- [ ] Tidak ada N+1, list paginated, search/filter server-side
- [ ] Sorting index (jika ada): trait `HasTableSorting` di component + whitelist `applySorting()` di Service + `<x-sortable-header>` indikator
- [ ] Halaman list punya toast "Ditemukan N data" saat search/filter berubah (pola `filterChanged`, notify di `render()` setelah paginator)
- [ ] Container scrollable pakai `custom-scrollbar` (tabel, dropdown panel, modal body panjang)
- [ ] Test dasar lulus + Pint bersih
- [ ] Tidak ada dead code / field mati / duplicate logic
- [ ] URL aman: ID terenkripsi, tidak ada ID mentah di URL/link

# Dilarang
- Menyisakan field mati / logic di Blade / hardcode role-permission di view
- Membuat duplicate component/service/trait
- Membuat fitur tanpa permission + Policy + `can:` middleware route
- Menyimpan file secara sinkron di request (harus lewat Job/worker)
- Membuat migration `add_*` baru untuk tabel yang schema-nya masih boleh diubah
- Menggunakan ID mentah (angka) di URL untuk model yang punya HasEncryptedRouteKey
- Membuat action button di loop tanpa wire:target/wire:key dan loading state
- Membuat action/navigasi button dengan raw `<a href>` (kecuali breadcrumb text link)
- Memakai modal untuk form kompleks (nested/repeater/upload file) -- gunakan full-page form component
- Membuat komponen Blade baru jika fungsi sudah ada di inventaris komponen
- Hardcode spacing/warna/typography yang inkonsisten dengan design system
- Membuat tabel tanpa overflow-x-auto (horizontal scroll di mobile)
- Membuat form grid tanpa breakpoint responsif (harus adaptif 1/2/3 kolom)
- Membuat `<x-text-input>` tanpa `placeholder` (kecuali type="date"/"hidden")
- Membuat field required tanpa `:required="true"` di `<x-input-label>`
- Menyisipkan gambar non-WebP ke dokumen Word (konversi dulu via intervention/image)
- Membuat migration dengan timestamp yang SAMA dengan migration lain (konflik di tabel `migrations` → salah satu migration bisa ter-skip & tabel tidak dibuat). WAJIB timestamp unik.
- Menggunakan `dark:bg-gray-900` untuk input field — WAJIB `dark:bg-gray-700` (konsisten dengan searchable-select & textarea)
- Menggunakan `rounded-md`/`rounded-xl` atau `focus:ring-indigo-*` di komponen form — WAJIB `rounded-lg` + `focus:ring-blue-500`
- `orderBy` manual/hardcode di Livewire component atau `<th>` sortable inline — WAJIB `HasTableSorting` + `SortsQuery` + `<x-sortable-header>`
- Sorting tanpa whitelist `$sortable` di Service (field bebas = celah SQL injection di orderByRaw)
- Memanggil notify hasil filter di hook `updating*` — hasil belum dihitung; notify HANYA di `render()` setelah query paginator
- Membuat mekanisme badge menu baru — WAJIB pakai key `'badge'` di `HasMenuItems` + listener di `getListeners()` yang sudah ada
- Mengubah path/payload endpoint `/sso` sepihak — kontrak dengan sso-server, perubahan harus dua sisi

# Modal Form Pattern (WAJIB ikuti pola master-data)
Untuk modal form tambah/edit data di halaman index, JANGAN pakai `<x-modal>` component (yang punya `x-transition` delay).
Gunakan pola `@if($showModal)` + `@entangle` + custom HTML seperti master-data:

```blade
@if($showModal)
    <div class="fixed inset-0 z-50 overflow-y-auto custom-scrollbar" x-data="{ show: @entangle('showModal') }">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" @click="$wire.closeModal()"></div>
            <div class="inline-block align-bottom w-full bg-white dark:bg-gray-800 rounded-lg text-left shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <form wire:submit="save">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
                            {{ $editMode ? 'Edit' : 'Tambah' }}
                        </h3>
                        {{-- form fields --}}
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse gap-2">
                        <x-loading-button type="submit" target="save" variant="primary" size="lg" loadingText="Menyimpan..." class="w-full sm:w-auto">
                            {{ $editMode ? 'Update' : 'Simpan' }}
                        </x-loading-button>
                        <x-cancel-button wire:click="closeModal" target="closeModal" variant="secondary" size="lg" class="mt-3 sm:mt-0 w-full sm:w-auto" />
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
```

Alasan: `@if($showModal)` = modal unload instant saat `showModal=false` (di re-render yang sama); `<x-modal>` pakai
`x-transition` → spinner hilang duluan sebelum modal tertutup. Backdrop click → `$wire.closeModal()`.
`<x-modal>` tetap boleh dipakai untuk modal NON-form (konfirmasi, preview).

# Sorting di Index (WAJIB pola ini — 3 lapisan, jangan tulis ulang)
- Component: pakai trait `App\Livewire\Traits\HasTableSorting` — menyediakan `$sortField`, `$sortDir`, dan `sortBy($field)`
  (klik kolom sama = balik arah, kolom baru = asc, otomatis `resetPage()`). JANGAN tulis ulang properti/method-nya manual.
  Set default kolom di `mount()` (mis. `$this->sortField = 'name';`), teruskan ke Service di `render()`.
- Header kolom sortable pakai `<x-sortable-header field="..." :sort-field="$sortField" :sort-dir="$sortDir">Label</x-sortable-header>`
  (indikator panah + spinner `wire:target="sortBy('...')"` built-in; prop `:compact="true"` untuk tabel `px-4 py-2`).
  Kolom non-sortable (mis. Aksi) tetap `<th>` biasa.
- Di Service: WAJIB whitelist via `SortsQuery::applySorting($query, $sortable, $sortField, $sortDir, $defaultField)`
  — `$sortable` = map `['field_ui' => 'kolom_sql']`; field tak dikenal fallback ke default. NULL dianggap terkecil;
  tie-break `id desc` menjaga urutan deterministik antar-halaman.
- Sorting kolom derived/agregat: pakai ekspresi SQL di value `$sortable` (subquery/orderByRaw), BUKAN sort di PHP.
  JANGAN pakai alias `withCount` di ekspresi — Postgres tidak resolve alias di `ORDER BY ... IS NULL`; tulis subquery COUNT-nya.
- Kolom many-to-many JANGAN dibuat sortable — urutan "item pertama" ambigu & subquery morph-type rapuh lintas DB.
- JANGAN langsung pakai input user ke `orderBy()` tanpa whitelist (celah SQL injection di orderByRaw).

# Custom Scrollbar (WAJIB dipakai di semua area scroll)
- CSS `.custom-scrollbar` sudah tersedia di `resources/css/app.css` (light + dark aware).
- WAJIB tambahkan class `custom-scrollbar` ke container yang scrollable: `overflow-x-auto` (tabel), `overflow-y-auto`
  (sidebar, dropdown panel, modal body panjang, chat).

# Dropdown Flip Positioning (WAJIB untuk dropdown/popover custom)
- Dropdown yang membuka ke bawah WAJIB deteksi ruang viewport: jika `spaceBelow < tinggiDropdown` DAN
  `spaceAbove > spaceBelow` → render dengan `bottom-full mb-2`, selain itu `top-full mt-2`.
- Pola ada di `<x-searchable-select>`, `<x-multi-searchable-select>` (`dropUp` + `checkPosition()` via
  `getBoundingClientRect`, re-check `@scroll.window`) dan `<x-filter-popover>` — pakai ulang pola yang sama,
  jangan bikin varian lain.

# HasUserStamps — created_by / updated_by (WAJIB di semua tabel entity)
- Trait `App\Traits\HasUserStamps` otomatis mengisi `created_by`/`updated_by` pada create/update dari user login.
- Migration entity WAJIB sertakan kedua kolom nullable `constrained('users')->nullOnDelete()`.
- Relasi `creator()` / `updater()` tersedia — pakai untuk tampilan "dibuat oleh / diubah oleh" di detail page & audit;
  eager load bila dipakai di list.
- Model bisa membatasi kolom via `protected array $userStamps` (mis. hanya `['updated_by']`).

# History/Recurring Records Pattern (laporan berulang, log aktivitas, dll)
Untuk data yang berulang/history (mis. log maintenance, aktivitas harian), JANGAN simpan sebagai field di model utama.
Buat tabel terpisah dengan relasi HasMany, dan kelola CRUD di halaman DETAIL/SHOW (bukan di form create/edit utama).

Pola:
1. Tabel terpisah dengan `foreignId('parent_id')->cascadeOnDelete()`.
2. Model terpisah dengan Enum untuk status/hasil.
3. Relasi HasMany di model utama, di-order by tanggal desc.
4. Accessor `latest_xxx` (eager-loaded) + derived status untuk index/list.
5. CRUD di Livewire Show/Detail component (modal form sederhana untuk add/edit, modal konfirmasi untuk delete).
6. Filter index pakai `whereHas('relasi', ...)`.
7. Export (Excel/PDF/Word) eager load relasi + pakai accessor derived status.
8. File terkait (jika ada) async via Job/worker (pola File Storage).
9. Permission: pakai permission `update` dari model utama (history adalah bagian dari manajemen entity tersebut).
