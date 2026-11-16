<?php

namespace Database\Factories;

use App\Enums\GalanganStatus;
use App\Models\Galangan;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalanganFactory extends Factory
{
    protected $model = Galangan::class;

    /**
     * Sentra galangan kapal di Indonesia: [kota, provinsi, lat, lng].
     */
    private const LOKASI = [
        ['Surabaya', 'Jawa Timur', -7.2050, 112.7350],
        ['Batam', 'Kepulauan Riau', 1.0850, 104.0300],
        ['Jakarta Utara', 'DKI Jakarta', -6.1050, 106.8800],
        ['Lamongan', 'Jawa Timur', -6.8750, 112.3600],
        ['Banyuwangi', 'Jawa Timur', -8.1450, 114.3950],
        ['Semarang', 'Jawa Tengah', -6.9500, 110.4200],
        ['Makassar', 'Sulawesi Selatan', -5.1200, 119.4100],
        ['Balikpapan', 'Kalimantan Timur', -1.2650, 116.8100],
    ];

    public function definition(): array
    {
        [$kota, $provinsi, $lat, $lng] = fake()->randomElement(self::LOKASI);

        return [
            'kode' => strtoupper(fake()->unique()->bothify('GLG-###??')),
            'nama' => 'Galangan '.fake()->company(),
            'alamat' => fake()->streetAddress().', '.$kota,
            'kota' => $kota,
            'provinsi' => $provinsi,
            'latitude' => $lat + fake()->randomFloat(4, -0.02, 0.02),
            'longitude' => $lng + fake()->randomFloat(4, -0.02, 0.02),
            'telepon' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'pic_name' => fake()->name(),
            'pic_phone' => fake()->phoneNumber(),
            'keterangan' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(GalanganStatus::cases()),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GalanganStatus::Active,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GalanganStatus::Inactive,
        ]);
    }

    public function tanpaLokasi(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
