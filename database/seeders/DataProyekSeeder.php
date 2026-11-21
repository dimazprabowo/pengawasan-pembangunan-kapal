<?php

namespace Database\Seeders;

use App\Enums\DrawingStatus;
use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Enums\PengujianStatus;
use App\Enums\RapatJenis;
use App\Models\DataKapal;
use App\Models\DrawingType;
use App\Models\MaterialCategory;
use App\Models\VendorLingkup;
use App\Services\KontrakPeriodeService;
use Illuminate\Database\Seeder;

/**
 * Contoh data proyek (drawing register, vendor, material, pengujian,
 * rapat) untuk data kapal pertama — sumber BAB 2 & 3 laporan bulanan.
 */
class DataProyekSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DrawingStatus::cases() as $status) {
            \App\Models\DrawingStatus::firstOrCreate(['nama' => $status->value]);
        }
        // Katalog material bersifat global — wajib ada meski blok per-kapal
        // di-skip (register material butuh status default untuk baris baru).
        foreach (MaterialKategori::cases() as $kategori) {
            MaterialCategory::firstOrCreate(['nama' => $kategori->value]);
        }
        foreach (MaterialStatus::cases() as $status) {
            \App\Models\MaterialStatus::firstOrCreate(['nama' => $status->value]);
        }
        foreach (PengujianStatus::cases() as $status) {
            \App\Models\PengujianStatus::firstOrCreate(['nama' => $status->value]);
        }
        foreach (RapatJenis::cases() as $jenis) {
            \App\Models\RapatJenis::firstOrCreate(['nama' => $jenis->value]);
        }
        // Katalog lingkup pekerjaan vendor bersifat global (dipakai lintas kapal).
        $lingkupIds = collect(['Steel Work & Fabrication', 'Piping System', 'Electrical Installation', 'Mechanical & Propulsion', 'Painting & Coating'])
            ->mapWithKeys(fn ($nama) => [$nama => VendorLingkup::firstOrCreate(['nama' => $nama])->id]);
        $kontrakService = app(KontrakPeriodeService::class);

        // Penandatangan lembar pengesahan laporan bulanan per kapal —
        // hanya diisi bila belum ada (idempotent).
        $penandatangan = [
            'Crew Boat 22 PAX' => [
                ['label' => 'Diperiksa oleh', 'nama' => 'Ir. H. Ahmad Surya', 'jabatan' => 'Surveyor Senior', 'instansi' => 'PT BKI (Persero)'],
                ['label' => 'Disahkan oleh', 'nama' => 'Drs. Budi Hartono, M.M.', 'jabatan' => 'Kepala Cabang', 'instansi' => 'PT BKI (Persero)'],
            ],
            'Crew Boat 50 PAX' => [
                ['label' => 'Diperiksa oleh', 'nama' => 'Ir. Slamet Widodo', 'jabatan' => 'Surveyor Senior', 'instansi' => 'PT BKI (Persero)'],
                ['label' => 'Disahkan oleh', 'nama' => 'Ir. Rina Marlina, M.T.', 'jabatan' => 'Kepala Cabang', 'instansi' => 'PT BKI (Persero)'],
            ],
        ];

        foreach (DataKapal::all() as $dataKapal) {
            if (empty($dataKapal->penandatangan) && isset($penandatangan[$dataKapal->nama])) {
                $dataKapal->update(['penandatangan' => $penandatangan[$dataKapal->nama]]);
            }

            $this->seedForKapal($dataKapal, $kontrakService->getTotalBulan($dataKapal), $lingkupIds);
        }
    }

    private function seedForKapal(DataKapal $dataKapal, int $totalBulan, $lingkupIds): void
    {
        if ($dataKapal->drawingRegisters()->exists()) {
            return;
        }

        // Drawing register — jenis bersama, dengan riwayat revisi awal.
        $types = collect(['Load Line', 'Machinery', 'Electrical'])->mapWithKeys(fn ($name) => [$name => DrawingType::firstOrCreate(['nama' => $name])]);
        foreach ([['General Arrangement Plan', 'Load Line'], ['Piping Diagram', 'Machinery'], ['Electrical Wiring Diagram', 'Electrical']] as [$nama, $jenis]) {
            $drawing = $dataKapal->drawingRegisters()->create(['nama' => $nama, 'drawing_type_id' => $types[$jenis]->id]);
            $drawing->revisions()->create(['revisi' => 0, 'tanggal' => $dataKapal->tanggal_mulai_pelaksanaan?->toDateString() ?? now()->toDateString(), 'drawing_status_id' => \App\Models\DrawingStatus::where('nama', DrawingStatus::InProgress->value)->value('id')]);
        }

        // Vendor — bulan_ke = bulan proyek vendor mulai dilibatkan (opsi dari rencana Kurva-S)
        foreach ([
            ['CV Baja Perkasa', 'Steel Work & Fabrication', '0812-3456-7890', 1],
            ['PT Sumber Teknik Nusantara', 'Piping System', '021-5551234', 2],
            ['PT Marine Electricindo', 'Electrical Installation', '031-7778899', 3],
        ] as [$nama, $lingkup, $kontak, $bulanKe]) {
            // bulan_ke harus ada di rencana Kurva-S (opsi = bulan_ke kurva_s_rencana)
            // — proyek pendek (mis. 50 hari ≈ 2 bulan) di-cap ke total bulannya.
            $dataKapal->projectVendors()->create([
                'nama' => $nama,
                'lingkup_id' => $lingkupIds[$lingkup],
                'kontak' => $kontak,
                'bulan_ke' => min($bulanKe, max(1, $totalBulan)),
            ]);
        }

        // Material — tanggal = estimasi/aktual kedatangan untuk logika
        // kumulatif laporan bulanan.
        $mulaiKontrak = $dataKapal->tanggal_mulai_pelaksanaan?->copy() ?? now();
        foreach ([
            [MaterialKategori::SteelPlate, 'Steel Plate AH36 12mm', 'Grade AH36, 12 x 1500 x 6000 mm', 45.5, 'Ton', MaterialStatus::Tiba, $mulaiKontrak->copy()->addDays(30)],
            [MaterialKategori::SteelPlate, 'Steel Plate AH36 8mm', 'Grade AH36, 8 x 1500 x 6000 mm', 30.0, 'Ton', MaterialStatus::Pengadaan, null],
            [MaterialKategori::SystemKomponen, 'Main Engine', '2 x 1350 kW, RPM Max 1100', 2, 'Unit', MaterialStatus::Direncanakan, null],
            [MaterialKategori::SystemKomponen, 'Rudder & Steering Gear', 'Sesuai approved maker list', 1, 'Set', MaterialStatus::Direncanakan, null],
            [MaterialKategori::Lainnya, 'Welding Consumable', 'E7018, batch pengadaan tahap 1', 800, 'Kg', MaterialStatus::Tiba, $mulaiKontrak->copy()->addDays(45)],
        ] as [$kat, $item, $spek, $jml, $satuan, $status, $tanggal]) {
            $dataKapal->projectMaterials()->create([
                'material_category_id' => MaterialCategory::where('nama', $kat->value)->value('id'),
                'nama_item' => $item,
                'spesifikasi' => $spek,
                'jumlah' => $jml,
                'satuan' => $satuan,
                'material_status_id' => \App\Models\MaterialStatus::where('nama', $status->value)->value('id'),
                'tanggal' => $tanggal?->toDateString(),
            ]);
        }

        // Pengujian
        foreach ([
            ['NDT Test Block A & B', PengujianStatus::Selesai, 'OK, 100% acceptable'],
            ['Leak Test Double Bottom Tank', PengujianStatus::Berjalan, null],
            ['Inclining Test', PengujianStatus::Direncanakan, null],
        ] as [$nama, $status, $hasil]) {
            $dataKapal->pengujians()->create([
                'nama_pengujian' => $nama,
                'pengujian_status_id' => \App\Models\PengujianStatus::where('nama', $status->value)->value('id'),
                'hasil' => $hasil,
            ]);
        }

        // Rapat — di dalam rentang kontrak: dihitung mundur dari tanggal
        // selesai kontrak bila proyek sudah berakhir, dari hari ini bila berjalan.
        $baseRapat = $dataKapal->tanggal_selesai_pelaksanaan?->isPast()
            ? $dataKapal->tanggal_selesai_pelaksanaan->copy()
            : now();
        foreach ([
            [RapatJenis::Mingguan, 'Ruang Dept. Quality Control Galangan', 'Pembahasan progress mingguan & kendala material'],
            [RapatJenis::Mingguan, 'Ruang Dept. Quality Control Galangan', 'Review drawing approval & jadwal cutting'],
            [RapatJenis::Bulanan, 'Ruang Meeting Owner', 'Evaluasi progress bulanan & rencana bulan berikutnya'],
        ] as $i => [$jenis, $lokasi, $topik]) {
            $dataKapal->rapats()->create([
                'tanggal' => $baseRapat->copy()->subDays(7 * ($i + 1)),
                'rapat_jenis_id' => \App\Models\RapatJenis::where('nama', $jenis->value)->value('id'),
                'lokasi' => $lokasi,
                'topik' => $topik,
            ]);
        }
    }
}
