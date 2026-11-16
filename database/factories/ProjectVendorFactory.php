<?php

namespace Database\Factories;

use App\Models\ProjectVendor;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectVendorFactory extends Factory
{
    protected $model = ProjectVendor::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->company(),
            'lingkup_pekerjaan' => fake()->randomElement(['Piping System', 'Electrical', 'Steel Work', 'Outfitting', 'Painting']),
            'kontak' => fake()->optional()->phoneNumber(),
        ];
    }
}
