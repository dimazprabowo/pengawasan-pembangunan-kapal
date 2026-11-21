<?php

namespace Database\Seeders;

use App\Enums\DataKapalStatus;
use App\Models\DataKapal;
use App\Models\Galangan;
use App\Models\Owner;
use Illuminate\Database\Seeder;

class DataKapalSeeder extends Seeder
{
    public function run(): void
    {
        $owners = Owner::all();
        $galangans = Galangan::all();

        if ($owners->isEmpty()) {
            $this->command->warn('No owners found. Please run OwnerSeeder first.');

            return;
        }

        if ($galangans->isEmpty()) {
            $this->command->warn('No galangan found. Please run GalanganSeeder first.');

            return;
        }

        $dataKapalData = [
            [
                'nama' => 'Crew Boat 22 PAX',
                'deskripsi' => 'Kapal crew boat dengan kapasitas 22 penumpang untuk transportasi pekerja offshore',
                'spesifikasi' => [
                    ['item' => 'Year of Build', 'keterangan' => '2024'],
                    ['item' => 'Material', 'keterangan' => 'Aluminium'],
                    ['item' => 'Speed Design', 'keterangan' => '28 knots'],
                    ['item' => 'Main Engine', 'keterangan' => '2 x Caterpillar C32, 1.450 HP @ 2.300 RPM'],
                    ['item' => 'Length Over All (LOA)', 'keterangan' => '24.00 m'],
                    ['item' => 'Beam (Moulded)', 'keterangan' => '6.50 m'],
                    ['item' => 'Depth (Moulded)', 'keterangan' => '2.80 m'],
                    ['item' => 'Draft (at Summer Load)', 'keterangan' => '1.20 m'],
                    ['item' => 'Bollard Pull', 'keterangan' => '10 Tons'],
                    ['item' => 'Complement', 'keterangan' => '4 Crew + 22 Passengers'],
                    ['item' => 'Cap. Fuel Oil (include Daily Tank)', 'keterangan' => '5.000 Liters'],
                    ['item' => 'Cap. Fresh Water', 'keterangan' => '1.500 Liters'],
                ],
                'nama_pekerjaan' => 'Jasa Konsultansi Pengawas Pembangunan 1 Unit Crew Boat 22 PAX',
                'nomor_kontrak_galangan' => 'BKI.CB22/PBG-2025/001',
                'nomor_kontrak_pengawas' => 'Sperj.110/UM.301/BKI-2025',
                'nomor_kontrak_gambar' => 'DWG-CB22/2025/014',
                'status' => DataKapalStatus::Active,
                // Proyek SELESAI: kontrak 50 hari, berakhir ~20 hari lalu —
                // seluruh minggu/bulan proyek punya laporan ter-seed.
                'tanggal_mulai_pelaksanaan' => now()->subDays(69)->toDateString(),
                'waktu_pelaksanaan_hari' => 50,
            ],
            [
                'nama' => 'Crew Boat 50 PAX',
                'deskripsi' => 'Kapal crew boat dengan kapasitas 50 penumpang untuk transportasi pekerja offshore',
                'spesifikasi' => [
                    ['item' => 'Year of Build', 'keterangan' => '2025'],
                    ['item' => 'Material', 'keterangan' => 'Steel'],
                    ['item' => 'Speed Design', 'keterangan' => '25 knots'],
                    ['item' => 'Main Engine', 'keterangan' => '3 x MTU 12V2000 M72, 1.800 HP @ 2.250 RPM'],
                    ['item' => 'Length Over All (LOA)', 'keterangan' => '35.00 m'],
                    ['item' => 'Beam (Moulded)', 'keterangan' => '8.00 m'],
                    ['item' => 'Depth (Moulded)', 'keterangan' => '3.50 m'],
                    ['item' => 'Draft (at Summer Load)', 'keterangan' => '1.80 m'],
                    ['item' => 'Bollard Pull', 'keterangan' => '18 Tons'],
                    ['item' => 'Complement', 'keterangan' => '6 Crew + 50 Passengers'],
                    ['item' => 'Cap. Fuel Oil (include Daily Tank)', 'keterangan' => '12.000 Liters'],
                    ['item' => 'Cap. Fresh Water', 'keterangan' => '3.000 Liters'],
                ],
                'nama_pekerjaan' => 'Jasa Konsultansi Pengawas Pembangunan 1 Unit Crew Boat 50 PAX',
                'nomor_kontrak_galangan' => 'BKI.CB50/PBG-2025/002',
                'nomor_kontrak_pengawas' => 'Sperj.215/UM.301/BKI-2025',
                'nomor_kontrak_gambar' => 'DWG-CB50/2025/021',
                'status' => DataKapalStatus::Active,
                // Proyek BERJALAN: kontrak 100 hari, sudah berjalan 88 hari —
                // aktual ter-seed ±88% (lihat LaporanMingguanSeeder).
                'tanggal_mulai_pelaksanaan' => now()->subDays(88)->toDateString(),
                'waktu_pelaksanaan_hari' => 100,
            ],
        ];

        // Cukup 2 proyek: satu per owner (owner ke-i dapat proyek ke-i).
        foreach ($owners as $index => $owner) {
            $data = $dataKapalData[$index] ?? null;
            if (! $data) {
                break;
            }

            DataKapal::firstOrCreate(
                [
                    'owner_id' => $owner->id,
                    'nama' => $data['nama'],
                ],
                [
                    'galangan_id' => $galangans->random()->id,
                    'deskripsi' => $data['deskripsi'],
                    'nama_pekerjaan' => $data['nama_pekerjaan'],
                    'nomor_kontrak_galangan' => $data['nomor_kontrak_galangan'],
                    'nomor_kontrak_pengawas' => $data['nomor_kontrak_pengawas'],
                    'nomor_kontrak_gambar' => $data['nomor_kontrak_gambar'],
                    'spesifikasi' => $data['spesifikasi'],
                    'status' => $data['status'],
                    'tanggal_mulai_pelaksanaan' => $data['tanggal_mulai_pelaksanaan'],
                    'waktu_pelaksanaan_hari' => $data['waktu_pelaksanaan_hari'],
                ]
            );
        }

        $this->command->info('Data Kapal seeded successfully.');
    }
}
