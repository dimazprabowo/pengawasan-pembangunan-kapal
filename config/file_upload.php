<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | This is the default configuration for file uploads across the application.
    | You can override these settings for specific fields below.
    |
    */
    'default' => [
        'max_size' => 2048, // KB (2 MB)
        'mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field-Specific File Upload Configuration
    |--------------------------------------------------------------------------
    |
    | Define specific upload rules for different fields in your application.
    | Each field can have its own max_size and allowed mimes.
    |
    | Usage in validation:
    | 'field_name' => file_upload_validation_rule('field_name', true)
    |
    */
    'fields' => [
        'foto_kapal' => [
            'max_size' => 20480, // 20 MB
            'mimes' => ['jpg', 'jpeg', 'png'],
        ],

        'sertifikat' => [
            'max_size' => 5120, // 5 MB
            'mimes' => ['pdf'],
        ],

        'dokumen_lain' => [
            // Uses default configuration
        ],

        'lampiran' => [
            'max_size' => 20480, // 20 MB
            'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'bmp', 'svg'],
        ],

        'template_laporan_jenis_kapal' => [
            'max_size' => 20480, // 20 MB
            'mimes' => ['doc', 'docx'],
        ],
    ],
];
