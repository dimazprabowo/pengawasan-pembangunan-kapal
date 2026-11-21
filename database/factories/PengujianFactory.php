<?php

namespace Database\Factories;

use App\Models\Pengujian;
use App\Models\PengujianStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PengujianFactory extends Factory
{
    protected $model = Pengujian::class;

    public function definition(): array
    {
        return [
            'nama_pengujian' => fake()->randomElement(['NDT Test', 'Leak Test', 'Hydrostatic Test', 'Inclining Test', 'Sea Trial']),
            'tanggal' => fake()->optional()->dateTimeBetween('-1 month', '+3 months'),
            'pengujian_status_id' => PengujianStatus::query()->inRandomOrder()->value('id')
                ?? PengujianStatus::create(['nama' => 'direncanakan'])->id,
            'hasil' => fake()->optional()->randomElement(['Lulus', 'Memuaskan', 'OK']),
        ];
    }
}
