<?php

namespace Database\Seeders;

use App\Enums\JenisKapalStatus;
use App\Models\Company;
use App\Models\Galangan;
use App\Models\JenisKapal;
use Illuminate\Database\Seeder;

class JenisKapalSeeder extends Seeder
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

        $jenisKapalData = [
            [
                'nama' => 'Crew Boat 22 PAX',
                'deskripsi' => 'Kapal crew boat dengan kapasitas 22 penumpang untuk transportasi pekerja offshore',
                'status' => JenisKapalStatus::Active,
            ],
            [
                'nama' => 'Crew Boat 50 PAX',
                'deskripsi' => 'Kapal crew boat dengan kapasitas 50 penumpang untuk transportasi pekerja offshore',
                'status' => JenisKapalStatus::Active,
            ],
        ];

        // Kontrak: mulai 77 hari lalu agar minggu proyek 1-10 sudah lewat (untuk seed laporan),
        // durasi 700 hari = 100 minggu proyek (sesuai template Kurva-S seeder).
        $tanggalMulaiKontrak = now()->subDays(77)->toDateString();
        $durasiKontrakHari = 700;

        foreach ($companies as $company) {
            foreach ($jenisKapalData as $data) {
                JenisKapal::firstOrCreate(
                    [
                        'company_id' => $company->id,
                        'nama' => $data['nama'],
                    ],
                    [
                        'galangan_id' => $galangans->random()->id,
                        'deskripsi' => $data['deskripsi'],
                        'status' => $data['status'],
                        'tanggal_mulai_kontrak' => $tanggalMulaiKontrak,
                        'durasi_kontrak_hari' => $durasiKontrakHari,
                    ]
                );
            }
        }

        $this->command->info('Jenis Kapal seeded successfully.');
    }
}
