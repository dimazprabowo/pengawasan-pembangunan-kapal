<?php

namespace Database\Factories;

use App\Enums\RapatJenis;
use App\Models\Rapat;
use Illuminate\Database\Eloquent\Factories\Factory;

class RapatFactory extends Factory
{
    protected $model = Rapat::class;

    public function definition(): array
    {
        return [
            'tanggal' => fake()->dateTimeBetween('-2 months', 'now'),
            'jenis' => fake()->randomElement(RapatJenis::cases()),
            'lokasi' => fake()->optional()->city(),
            'topik' => fake()->optional()->sentence(5),
        ];
    }
}
