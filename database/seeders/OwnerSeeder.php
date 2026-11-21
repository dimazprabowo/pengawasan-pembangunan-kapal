<?php

namespace Database\Seeders;

use App\Models\Owner;
use Illuminate\Database\Seeder;

class OwnerSeeder extends Seeder
{
    public function run(): void
    {
        $owners = [
            [
                'code' => 'ACME',
                'name' => 'PT Acme Corporation',
                'email' => 'info@acme.co.id',
                'phone' => '021-5551234',
                'address' => 'Jl. Sudirman No. 123, Jakarta',
                'pic_name' => 'John Doe',
                'pic_email' => 'john@acme.co.id',
                'pic_phone' => '081234567890',
                'status' => 'active',
            ],
            [
                'code' => 'SMPL',
                'name' => 'PT Sample Indonesia',
                'email' => 'contact@sample.co.id',
                'phone' => '021-5559876',
                'address' => 'Jl. Gatot Subroto No. 45, Jakarta',
                'pic_name' => 'Jane Smith',
                'pic_email' => 'jane@sample.co.id',
                'pic_phone' => '081298765432',
                'status' => 'active',
            ],
        ];

        foreach ($owners as $owner) {
            Owner::firstOrCreate(
                ['code' => $owner['code']],
                collect($owner)->except('code')->toArray()
            );
        }
    }
}
