<?php

namespace Database\Seeders;

use App\Enums\DataKapalStatus;
use App\Models\Company;
use App\Models\DataKapal;
use App\Models\Galangan;
use Illuminate\Database\Seeder;

class DataKapalSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::all();
        $galangans = Galangan::all();

        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run CompanySeeder first.');

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
                'status' => DataKapalStatus::Active,
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
                'status' => DataKapalStatus::Active,
            ],
        ];

        // Kontrak: 100 hari penuh mulai 99 hari lalu, berakhir hari ini — seluruh
        // minggu/bulan proyek punya laporan ter-seed (simulasi proyek selesai).
        $tanggalMulaiKontrak = now()->subDays(99)->toDateString();
        $durasiKontrakHari = 100;

        foreach ($companies as $company) {
            foreach ($dataKapalData as $data) {
                DataKapal::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'nama' => $data['nama'],
                    ],
                    [
                        'galangan_id' => $galangans->random()->id,
                        'deskripsi' => $data['deskripsi'],
                        'spesifikasi' => $data['spesifikasi'],
                        'status' => $data['status'],
                        'tanggal_mulai_kontrak' => $tanggalMulaiKontrak,
                        'durasi_kontrak_hari' => $durasiKontrakHari,
                    ]
                );
            }
        }

        $this->command->info('Data Kapal seeded successfully.');
    }
}
