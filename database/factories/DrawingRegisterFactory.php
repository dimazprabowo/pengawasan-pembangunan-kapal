<?php

namespace Database\Factories;

use App\Enums\DrawingStatus;
use App\Models\DrawingRegister;
use Illuminate\Database\Eloquent\Factories\Factory;

class DrawingRegisterFactory extends Factory
{
    protected $model = DrawingRegister::class;

    public function definition(): array
    {
        return [
            'nomor' => 'DWG-'.fake()->unique()->numerify('####'),
            'judul' => fake()->sentence(4),
            'status_produksi' => fake()->randomElement(DrawingStatus::cases()),
            'status_review_mno' => fake()->randomElement(DrawingStatus::cases()),
            'status_approval_klas' => fake()->randomElement(DrawingStatus::cases()),
            'mno_comment_open' => fake()->numberBetween(0, 3),
            'mno_comment_closed' => fake()->numberBetween(0, 10),
            'mno_comment_informasi' => fake()->numberBetween(0, 2),
            'klas_comment_open' => fake()->numberBetween(0, 3),
            'klas_comment_closed' => fake()->numberBetween(0, 10),
            'klas_comment_informasi' => fake()->numberBetween(0, 2),
        ];
    }
}
