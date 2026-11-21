<?php

namespace Database\Factories;

use App\Models\ProjectVendor;
use App\Models\VendorLingkup;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectVendorFactory extends Factory
{
    protected $model = ProjectVendor::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->company(),
            'lingkup_id' => fn () => VendorLingkup::inRandomOrder()->value('id')
                ?? VendorLingkup::create(['nama' => fake()->unique()->words(2, true)])->id,
            'kontak' => fake()->optional()->phoneNumber(),
        ];
    }
}
