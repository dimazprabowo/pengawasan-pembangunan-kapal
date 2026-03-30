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
            [
                'nama' => 'Supply Vessel',
                'deskripsi' => 'Kapal supply untuk pengiriman material dan peralatan ke platform offshore',
                'status' => JenisKapalStatus::Active,
            ],
            [
                'nama' => 'Tug Boat',
                'deskripsi' => 'Kapal tunda untuk membantu manuver kapal besar di pelabuhan',
                'status' => JenisKapalStatus::Active,
            ],
            [
                'nama' => 'Barge',
                'deskripsi' => 'Kapal tongkang untuk pengangkutan kargo dalam jumlah besar',
                'status' => JenisKapalStatus::Active,
            ],
        ];

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
                    ]
                );
            }
        }

        $this->command->info('Jenis Kapal seeded successfully.');
    }
}
