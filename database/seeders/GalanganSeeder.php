<?php

namespace Database\Seeders;

use App\Models\Galangan;
use Illuminate\Database\Seeder;

class GalanganSeeder extends Seeder
{
    public function run(): void
    {
        Galangan::factory()->count(15)->create();
    }
}
