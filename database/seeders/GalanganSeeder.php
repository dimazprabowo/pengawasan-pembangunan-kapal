<?php

namespace Database\Seeders;

use App\Enums\GalanganStatus;
use App\Models\Galangan;
use Illuminate\Database\Seeder;

class GalanganSeeder extends Seeder
{
    /**
     * Galangan contoh dengan titik lokasi untuk peta dashboard (idempoten per kode).
     */
    private const DATA = [
        [
            'kode' => 'GLG-SBY01', 'nama' => 'Galangan Tanjung Perak Surabaya',
            'alamat' => 'Jl. Ujung, Kawasan Pelabuhan Tanjung Perak', 'kota' => 'Surabaya', 'provinsi' => 'Jawa Timur',
            'latitude' => -7.1987, 'longitude' => 112.7372, 'pic_name' => 'Ir. Budi Santoso', 'telepon' => '031-3291234',
        ],
        [
            'kode' => 'GLG-BTM01', 'nama' => 'Galangan Tanjung Uncang Batam',
            'alamat' => 'Kawasan Industri Galangan Tanjung Uncang', 'kota' => 'Batam', 'provinsi' => 'Kepulauan Riau',
            'latitude' => 1.0436, 'longitude' => 103.9306, 'pic_name' => 'Hendra Wijaya', 'telepon' => '0778-391234',
        ],
        [
            'kode' => 'GLG-JKT01', 'nama' => 'Galangan Tanjung Priok Jakarta',
            'alamat' => 'Jl. Raya Pelabuhan, Tanjung Priok', 'kota' => 'Jakarta Utara', 'provinsi' => 'DKI Jakarta',
            'latitude' => -6.1045, 'longitude' => 106.8865, 'pic_name' => 'Agus Prasetyo', 'telepon' => '021-4301234',
        ],
        [
            'kode' => 'GLG-LMG01', 'nama' => 'Galangan Paciran Lamongan',
            'alamat' => 'Jl. Raya Daendels, Paciran', 'kota' => 'Lamongan', 'provinsi' => 'Jawa Timur',
            'latitude' => -6.8768, 'longitude' => 112.3581, 'pic_name' => 'Slamet Riyadi', 'telepon' => '0322-661234',
        ],
        [
            'kode' => 'GLG-BWI01', 'nama' => 'Galangan Ketapang Banyuwangi',
            'alamat' => 'Jl. Gatot Subroto, Ketapang', 'kota' => 'Banyuwangi', 'provinsi' => 'Jawa Timur',
            'latitude' => -8.1415, 'longitude' => 114.3990, 'pic_name' => 'Dewi Lestari', 'telepon' => '0333-421234',
        ],
    ];

    public function run(): void
    {
        foreach (self::DATA as $data) {
            Galangan::updateOrCreate(
                ['kode' => $data['kode']],
                [...$data, 'status' => GalanganStatus::Active]
            );
        }
    }
}
