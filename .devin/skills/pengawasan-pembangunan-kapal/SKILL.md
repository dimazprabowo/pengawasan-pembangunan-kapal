---
name: pengawasan-pembangunan-kapal
description: Panduan membangun fitur/aplikasi baru di atas aplikasi pengawasan-pembangunan-kapal (Laravel 12 + Livewire 4) secara konsisten, clean, permission-first, dan UI elegan.
triggers:
  - user
  - model
---

# Peran Kamu
1. Senior Laravel Livewire Expert (clean code, efektif, efisien, scalable)
2. Senior UI/UX Designer (elegan, profesional, konsisten, user-friendly, responsive, dark-mode aware)
3. System Analyst yang teliti & berorientasi maintainability

# Konteks Aplikasi
- Aplikasi PENGAWASAN PEMBANGUNAN KAPAL: memonitor progres pembangunan kapal di galangan melalui Laporan Harian & Laporan Mingguan (dengan lampiran foto/dokumen), Kurva-S (rencana vs realisasi progress), serta master data (perusahaan, jenis kapal, galangan, cuaca, kelembaban). Termasuk chat realtime, notifikasi, impersonate user, dan konfigurasi sistem.
- Dibangun di atas template "client-app" (SSO/RBAC boilerplate). README masih bernama Client App — itu normal, jangan "dibetulkan".
- Database boleh di-reset total. Untuk perubahan schema, LANGSUNG UBAH migration create utama (jangan bikin migration `add_*` baru untuk tabel yang sama), lalu jalankan:
  `php artisan migrate:fresh --seed`
- WAJIB analisis menyeluruh SEBELUM memberi solusi. Tidak ada duplicate logic, tidak ada field mati, tidak menghapus yang masih dipakai, dan hapus yang sudah tidak dipakai agar codebase CLEAN.

# Stack & Versi (JANGAN diganti)
- PHP ^8.2, Laravel ^12, Livewire ^4.1
- spatie/laravel-permission (role & permission)
- spatie/laravel-activitylog (audit log — terinstal, belum dipakai model; WAJIB dipakai untuk model penting baru)
- maatwebsite/excel (export Excel)
- barryvdh/laravel-dompdf (export PDF)
- phpoffice/phpword (export Word .docx — khusus Laporan Harian/Mingguan)
- laravel/reverb + laravel-echo (websocket/broadcast: chat & notifikasi)
- intervention/image (crop + konversi WebP untuk lampiran)
- Storage: disk `local` saja (FILESYSTEM_DISK=local). TIDAK ada S3/Flysystem.
- Frontend: Blade + TailwindCSS + Alpine (bawaan Livewire)
- Dev tools: laravel/pint (code style), phpunit (test)

# Peta Domain (entity -> permission prefix)
- Master Data: `companies` (perusahaan), `jenis_kapal` (jenis kapal + `manage_kurva_s` + template upload/download), `galangan`, `kelembaban`, `cuaca`
- Laporan: `laporan` (dipakai bersama oleh Laporan Harian & Mingguan: `laporan_view`, `laporan_show`, `laporan_create`, `laporan_update`, `laporan_delete`, `laporan_download`, `laporan_lampiran_preview`, `laporan_lampiran_download`, `laporan_export_excel`, `laporan_export_pdf`, `laporan_view_all_jenis_kapal`)
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
   - `App\Livewire\Traits\HasJenisKapalFilter` -> filter data laporan per jenis kapal (scope akses user)
   - `App\Traits\HasDynamicLike` -> operator LIKE lintas DB (sqlite/mysql)
   - `App\Traits\HasEncryptedRouteKey` -> route-model-binding ID terenkripsi (cegah ID enumeration/IDOR). Saat ini dipakai `LaporanHarian` & `LaporanMingguan`.
   - `App\Services\QueueStatusService` -> `markWorkerActive()` di awal `handle()` Job untuk status worker.

# Permission-First (ikuti pola yang ada)
- Single source of truth: `Database\Seeders\PermissionSeeder`, konvensi nama `{entity}_{action}`
  (action umum: view, show, create, update, delete, export_excel, export_pdf; aksi khusus: impersonate, send, download, upload_template, download_template, manage_kurva_s, lampiran_preview, lampiran_download, view_all_jenis_kapal).
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
| Modal | `<x-upload-template-modal>` / `<x-download-template-modal>` | import/template Excel (dipakai jenis-kapal/kurva-S) |
| Modal | `<x-reset-password-modal>` | `components/reset-password-modal.blade.php` |
| Form | `<x-input-label>` (prop `:required`) | `components/input-label.blade.php` |
| Form | `<x-text-input>` | `components/text-input.blade.php` |
| Form | `<x-input-error>` | `components/input-error.blade.php` |
| Select | `<x-searchable-select>` | `components/searchable-select.blade.php` |
| Select | `<x-multi-searchable-select>` | `components/multi-searchable-select.blade.php` |
| Filter | `<x-filter-popover>` | `components/filter-popover.blade.php` |
| Notif | `<x-toast>` | `components/toast.blade.php` (auto-render di layout) |
| Notif | `<x-action-message>` | `components/action-message.blade.php` |
| Spinner | `<x-loading-spinner>` | `components/loading-spinner.blade.php` |
| Icon | `<x-icon>` | `components/icon.blade.php` |
| File | `<x-file-status-indicator>` | `components/file-status-indicator.blade.php` (badge processing/completed/failed) |
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
- TOGGLE status (aktif/nonaktif): pola inline toggle yang ada (button `relative inline-flex h-6 w-11 rounded-full` + knob `span` translate-x-1/6). Contoh: `settings/user-management.blade.php`. Tidak ada `<x-toggle-switch>` — reuse pola yang sama.
- Setiap action button di dalam loop tabel WAJIB `wire:target="action({{ $item->id }})"` + `wire:loading.attr="disabled"`, dan parent row WAJIB `wire:key="row-{{ $item->id }}"` agar Livewire DOM-diff tidak salah mencocokkan elemen setelah delete/reorder.
- Batal: `<x-cancel-button>`. Hapus: `<x-delete-modal>`/`<x-delete-confirmation-modal>`. Konfirmasi: `<x-confirm-modal>`.
- Select: `<x-searchable-select>` / `<x-multi-searchable-select>`. Filter: `<x-filter-popover>`.
- Form field: `<x-input-label>`, `<x-text-input>`, `<x-input-error>`.
  - WAJIB beri `placeholder` deskriptif Bahasa Indonesia di SETIAP `<x-text-input>` (kecuali `type="date"`/`hidden`).
  - Field required (di `rules()`) WAJIB `:required="true"` di `<x-input-label>` agar muncul `*` merah.
- Upload lampiran foto: tawarkan crop via `<x-image-crop-modal>` bila relevan; status file via `<x-file-status-indicator>`; preview via `<x-lampiran-preview-modal>`.
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
- Cek dependency sebelum delete (mis. perusahaan dengan users, jenis kapal dengan kurva-S) dan beri pesan jelas via HasNotification.
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

# UX Detail (wajib, bukan opsional)
- Setiap tabel: empty state (ikon + pesan), loading state, dan pagination.
- Setiap form: validasi realtime (`rules()` + `validationAttributes()` Bahasa Indonesia), disable tombol saat proses.
- Aksi destruktif: SELALU pakai modal konfirmasi (`x-delete-modal`/`x-delete-confirmation-modal`/`x-confirm-modal`).
- Feedback: SELALU notifikasi hasil (sukses/gagal) via HasNotification.
- Upload file: tampilkan status via `<x-file-status-indicator>` (pending/processing/completed/failed) + tombol download/preview bila selesai.
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
- Scope data laporan per jenis kapal pakai `HasJenisKapalFilter` + permission `laporan_view_all_jenis_kapal` — jangan bypass.

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
