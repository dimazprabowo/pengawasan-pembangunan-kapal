<?php

namespace Database\Seeders;

use App\Enums\DrawingStatus;
use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Enums\PengujianStatus;
use App\Enums\RapatJenis;
use App\Models\DataKapal;
use Illuminate\Database\Seeder;

/**
 * Contoh data proyek (drawing register, vendor, material, pengujian,
 * rapat) untuk data kapal pertama — sumber BAB 2 & 3 laporan bulanan.
 */
class DataProyekSeeder extends Seeder
{
    public function run(): void
    {
        $dataKapal = DataKapal::first();
        if (! $dataKapal) {
            return;
        }

        if ($dataKapal->drawingRegisters()->exists()) {
            return;
        }

        // Drawing register — 6 gambar, tahap berbeda-beda
        foreach ([
            ['DWG-001', 'General Arrangement Plan', DrawingStatus::Selesai, DrawingStatus::Selesai, DrawingStatus::Selesai, 0, 3, 0, 0, 2, 0],
            ['DWG-002', 'Midship Section', DrawingStatus::Selesai, DrawingStatus::Selesai, DrawingStatus::Proses, 1, 4, 0, 1, 1, 0],
            ['DWG-003', 'Construction Profile', DrawingStatus::Selesai, DrawingStatus::Proses, DrawingStatus::Belum, 2, 1, 1, 0, 0, 0],
            ['DWG-004', 'Shell Expansion', DrawingStatus::Proses, DrawingStatus::Belum, DrawingStatus::Belum, 0, 0, 0, 0, 0, 0],
            ['DWG-005', 'Piping Diagram', DrawingStatus::Proses, DrawingStatus::Belum, DrawingStatus::Belum, 0, 0, 0, 0, 0, 0],
            ['DWG-006', 'Electrical Wiring Diagram', DrawingStatus::Belum, DrawingStatus::Belum, DrawingStatus::Belum, 0, 0, 0, 0, 0, 0],
        ] as [$nomor, $judul, $prod, $mno, $klas, $mo, $mc, $mi, $ko, $kc, $ki]) {
            $dataKapal->drawingRegisters()->create([
                'nomor' => $nomor,
                'judul' => $judul,
                'status_produksi' => $prod,
                'status_review_mno' => $mno,
                'status_approval_klas' => $klas,
                'mno_comment_open' => $mo,
                'mno_comment_closed' => $mc,
                'mno_comment_informasi' => $mi,
                'klas_comment_open' => $ko,
                'klas_comment_closed' => $kc,
                'klas_comment_informasi' => $ki,
            ]);
        }

        // Vendor
        foreach ([
            ['PT Sumber Teknik Nusantara', 'Piping System', '021-5551234'],
            ['CV Baja Perkasa', 'Steel Work & Fabrication', '0812-3456-7890'],
            ['PT Marine Electricindo', 'Electrical Installation', '031-7778899'],
        ] as [$nama, $lingkup, $kontak]) {
            $dataKapal->projectVendors()->create([
                'nama' => $nama,
                'lingkup_pekerjaan' => $lingkup,
                'kontak' => $kontak,
            ]);
        }

        // Material
        foreach ([
            [MaterialKategori::SteelPlate, 'Steel Plate AH36 12mm', 'Grade AH36, 12 x 1500 x 6000 mm', 45.5, 'Ton', MaterialStatus::Tiba],
            [MaterialKategori::SteelPlate, 'Steel Plate AH36 8mm', 'Grade AH36, 8 x 1500 x 6000 mm', 30.0, 'Ton', MaterialStatus::Pengadaan],
            [MaterialKategori::SystemKomponen, 'Main Engine', '2 x 1350 kW, RPM Max 1100', 2, 'Unit', MaterialStatus::Direncanakan],
            [MaterialKategori::SystemKomponen, 'Rudder & Steering Gear', 'Sesuai approved maker list', 1, 'Set', MaterialStatus::Direncanakan],
            [MaterialKategori::Lainnya, 'Welding Consumable', 'E7018, batch pengadaan tahap 1', 800, 'Kg', MaterialStatus::Tiba],
        ] as [$kat, $item, $spek, $jml, $satuan, $status]) {
            $dataKapal->projectMaterials()->create([
                'kategori' => $kat,
                'nama_item' => $item,
                'spesifikasi' => $spek,
                'jumlah' => $jml,
                'satuan' => $satuan,
                'status' => $status,
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
                'status' => $status,
                'hasil' => $hasil,
            ]);
        }

        // Rapat
        foreach ([
            [RapatJenis::Mingguan, 'Ruang Dept. Quality Control Galangan', 'Pembahasan progress mingguan & kendala material'],
            [RapatJenis::Mingguan, 'Ruang Dept. Quality Control Galangan', 'Review drawing approval & jadwal cutting'],
            [RapatJenis::Bulanan, 'Ruang Meeting Owner', 'Evaluasi progress bulanan & rencana bulan berikutnya'],
        ] as $i => [$jenis, $lokasi, $topik]) {
            $dataKapal->rapats()->create([
                'tanggal' => now()->subDays(7 * ($i + 1)),
                'jenis' => $jenis,
                'lokasi' => $lokasi,
                'topik' => $topik,
            ]);
        }
    }
}
