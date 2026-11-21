<?php

namespace Database\Factories;

use App\Models\DrawingRegister;
use App\Models\DrawingType;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawingRegisterFactory extends Factory
{
    protected $model = DrawingRegister::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->sentence(4),
            'drawing_type_id' => fn () => DrawingType::firstOrCreate(['nama' => 'Load Line'])->id,
        ];
    }
}
