<?php

namespace Database\Factories;

use App\Enums\GalanganStatus;
use App\Models\Galangan;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalanganFactory extends Factory
{
    protected $model = Galangan::class;

    public function definition(): array
    {
        return [
            'kode' => strtoupper(fake()->unique()->bothify('GLG-###??')),
            'nama' => 'Galangan ' . fake()->company(),
            'alamat' => fake()->address(),
            'kota' => fake()->city(),
            'provinsi' => fake()->state(),
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
}
