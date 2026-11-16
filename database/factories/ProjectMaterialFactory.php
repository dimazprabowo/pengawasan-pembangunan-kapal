<?php

namespace Database\Factories;

use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Models\ProjectMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMaterialFactory extends Factory
{
    protected $model = ProjectMaterial::class;

    public function definition(): array
    {
        return [
            'kategori' => fake()->randomElement(MaterialKategori::cases()),
            'nama_item' => fake()->words(3, true),
            'spesifikasi' => fake()->optional()->sentence(3),
            'jumlah' => fake()->optional()->randomFloat(2, 1, 500),
            'satuan' => fake()->optional()->randomElement(['Ton', 'Pcs', 'Set', 'Meter']),
            'status' => fake()->randomElement(MaterialStatus::cases()),
            'tanggal' => fake()->optional()->dateTimeBetween('-1 month', '+3 months'),
        ];
    }
}
