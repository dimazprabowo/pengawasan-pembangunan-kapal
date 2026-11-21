<?php

namespace Database\Factories;

use App\Enums\MaterialKategori;
use App\Enums\MaterialStatus;
use App\Models\MaterialCategory;
use App\Models\MaterialStatus as MaterialStatusModel;
use App\Models\ProjectMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectMaterialFactory extends Factory
{
    protected $model = ProjectMaterial::class;

    public function definition(): array
    {
        return [
            'material_category_id' => MaterialCategory::firstOrCreate(['nama' => fake()->randomElement(MaterialKategori::cases())->value])->id,
            'nama_item' => fake()->words(3, true),
            'spesifikasi' => fake()->optional()->sentence(3),
            'jumlah' => fake()->optional()->randomFloat(2, 1, 500),
            'satuan' => fake()->optional()->randomElement(['Ton', 'Pcs', 'Set', 'Meter']),
            'material_status_id' => MaterialStatusModel::firstOrCreate(['nama' => fake()->randomElement(MaterialStatus::cases())->value])->id,
            'tanggal' => fake()->optional()->dateTimeBetween('-1 month', '+3 months'),
        ];
    }
}
