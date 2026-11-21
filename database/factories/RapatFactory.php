<?php

namespace Database\Factories;

use App\Models\Rapat;
use App\Models\RapatJenis;
use Illuminate\Database\Eloquent\Factories\Factory;

class RapatFactory extends Factory
{
    protected $model = Rapat::class;

    public function definition(): array
    {
        return [
            'tanggal' => fake()->dateTimeBetween('-2 months', 'now'),
            'rapat_jenis_id' => RapatJenis::inRandomOrder()->value('id') ?? RapatJenis::create(['nama' => 'mingguan'])->id,
            'lokasi' => fake()->optional()->city(),
            'topik' => fake()->optional()->sentence(5),
        ];
    }
}
