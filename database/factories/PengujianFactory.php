<?php

namespace Database\Factories;

use App\Enums\PengujianStatus;
use App\Models\Pengujian;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengujianFactory extends Factory
{
    protected $model = Pengujian::class;

    public function definition(): array
    {
        return [
            'nama_pengujian' => fake()->randomElement(['NDT Test', 'Leak Test', 'Hydrostatic Test', 'Inclining Test', 'Sea Trial']),
            'tanggal' => fake()->optional()->dateTimeBetween('-1 month', '+3 months'),
            'status' => fake()->randomElement(PengujianStatus::cases()),
            'hasil' => fake()->optional()->randomElement(['Lulus', 'Memuaskan', 'OK']),
        ];
    }
}
