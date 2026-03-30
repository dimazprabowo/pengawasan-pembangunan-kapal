<?php

namespace Database\Seeders;

use App\Models\Cuaca;
use Illuminate\Database\Seeder;

class CuacaSeeder extends Seeder
{
    public function run(): void
    {
        $cuacaData = [
            [
                'nama' => 'Cerah',
                'keterangan' => 'Cuaca cerah tanpa awan',
                'status' => 'active',
            ],
            [
                'nama' => 'Berawan',
                'keterangan' => 'Cuaca berawan sebagian',
                'status' => 'active',
            ],
            [
                'nama' => 'Mendung',
                'keterangan' => 'Cuaca mendung penuh awan',
                'status' => 'active',
            ],
            [
                'nama' => 'Hujan Ringan',
                'keterangan' => 'Hujan dengan intensitas ringan',
                'status' => 'active',
            ],
            [
                'nama' => 'Hujan Sedang',
                'keterangan' => 'Hujan dengan intensitas sedang',
                'status' => 'active',
            ],
            [
                'nama' => 'Hujan Lebat',
                'keterangan' => 'Hujan dengan intensitas lebat',
                'status' => 'active',
            ],
            [
                'nama' => 'Badai',
                'keterangan' => 'Cuaca buruk dengan angin kencang',
                'status' => 'active',
            ],
        ];

        foreach ($cuacaData as $data) {
            Cuaca::firstOrCreate(
                ['nama' => $data['nama']],
                $data
            );
        }
    }
}
