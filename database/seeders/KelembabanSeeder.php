<?php

namespace Database\Seeders;

use App\Models\Kelembaban;
use Illuminate\Database\Seeder;

class KelembabanSeeder extends Seeder
{
    public function run(): void
    {
        $kelembabanData = [
            [
                'nama' => 'Rendah',
                'nilai' => '< 30%',
                'keterangan' => 'Kelembaban udara rendah',
                'status' => 'active',
            ],
            [
                'nama' => 'Sedang',
                'nilai' => '30-60%',
                'keterangan' => 'Kelembaban udara sedang',
                'status' => 'active',
            ],
            [
                'nama' => 'Tinggi',
                'nilai' => '60-80%',
                'keterangan' => 'Kelembaban udara tinggi',
                'status' => 'active',
            ],
            [
                'nama' => 'Sangat Tinggi',
                'nilai' => '> 80%',
                'keterangan' => 'Kelembaban udara sangat tinggi',
                'status' => 'active',
            ],
        ];

        foreach ($kelembabanData as $data) {
            Kelembaban::firstOrCreate(
                ['nama' => $data['nama']],
                $data
            );
        }
    }
}
