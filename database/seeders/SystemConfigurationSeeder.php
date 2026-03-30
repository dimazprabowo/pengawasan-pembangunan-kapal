<?php

namespace Database\Seeders;

use App\Models\SystemConfiguration;
use Illuminate\Database\Seeder;

class SystemConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            // General configurations
            [
                'key' => 'app.name',
                'category' => 'general',
                'value' => 'Boilerplate',
                'data_type' => 'string',
                'description' => 'Nama aplikasi',
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'key' => 'app.timezone',
                'category' => 'general',
                'value' => 'Asia/Jakarta',
                'data_type' => 'string',
                'description' => 'Timezone aplikasi',
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'key' => 'file.max_upload_size',
                'category' => 'general',
                'value' => '20',
                'data_type' => 'integer',
                'description' => 'Maksimal ukuran upload file (MB)',
                'is_editable' => true,
                'is_active' => true,
            ],
            [
                'key' => 'file.allowed_mimes',
                'category' => 'general',
                'value' => 'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,gif,bmp,svg',
                'data_type' => 'string',
                'description' => 'Tipe file yang diizinkan untuk upload (pisahkan dengan koma)',
                'is_editable' => true,
                'is_active' => true,
            ],
        ];

        foreach ($configurations as $config) {
            SystemConfiguration::firstOrCreate(
                ['key' => $config['key']],
                collect($config)->except('key')->toArray()
            );
        }
    }
}
