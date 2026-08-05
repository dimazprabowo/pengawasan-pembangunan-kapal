<?php

if (!function_exists('email_logo_url')) {
    /**
     * Get the public URL for the email logo
     * Logo is hosted on DigitalOcean Spaces (S3) for email client compatibility
     */
    function email_logo_url(): string
    {
        return 'https://statutoria-monitoring-bucket.sgp1.digitaloceanspaces.com/assets/bki-logo.png';
    }
}

if (!function_exists('get_max_upload_size')) {
    /**
     * Get maximum upload size in KB for a specific field
     * 
     * @param string|null $fieldName Field name from config/file_upload.php
     * @return int Size in KB
     */
    function get_max_upload_size(?string $fieldName = null): int
    {
        $config = config('file_upload.fields.' . $fieldName, config('file_upload.default'));
        return (int) $config['max_size'];
    }
}

if (!function_exists('get_allowed_mimes')) {
    /**
     * Get allowed MIME types for a specific field
     * Returns comma-separated string
     * 
     * @param string|null $fieldName Field name from config/file_upload.php
     * @return string
     */
    function get_allowed_mimes(?string $fieldName = null): string
    {
        $config = config('file_upload.fields.' . $fieldName, config('file_upload.default'));
        return implode(',', $config['mimes']);
    }
}

if (!function_exists('get_allowed_mimes_array')) {
    /**
     * Get allowed MIME types as array for a specific field
     * 
     * @param string|null $fieldName Field name from config/file_upload.php
     * @return array
     */
    function get_allowed_mimes_array(?string $fieldName = null): array
    {
        $config = config('file_upload.fields.' . $fieldName, config('file_upload.default'));
        return $config['mimes'];
    }
}

if (!function_exists('file_upload_validation_rule')) {
    /**
     * Get complete file upload validation rule for a specific field.
     * Returns an array of rules (string + closure) to handle degraded uploads.
     *
     * When Livewire's temporary file upload fails silently (e.g. Windows path
     * length limits), the file property degrades from a TemporaryUploadedFile
     * object to a plain string. The closure skips validation for such cases
     * since the UI already shows "File gagal diproses" for non-object values.
     *
     * @param string|null $fieldName Field name from config/file_upload.php (e.g., 'foto_kapal', 'sertifikat', 'lampiran')
     * @param bool $required Whether file is required
     * @return array
     */
    function file_upload_validation_rule(?string $fieldName = null, bool $required = false): array
    {
        $maxSize = get_max_upload_size($fieldName);
        $allowedMimes = get_allowed_mimes_array($fieldName);

        return [
            $required ? 'required' : 'nullable',
            function ($attribute, $value, $fail) use ($maxSize, $allowedMimes) {
                if ($value === null) {
                    return;
                }

                if (!($value instanceof \Illuminate\Http\UploadedFile)) {
                    return;
                }

                if ($value->getSize() / 1024 > $maxSize) {
                    $fail('The :attribute must not be larger than ' . number_format($maxSize / 1024, 0) . ' MB.');
                    return;
                }

                $ext = strtolower($value->getClientOriginalExtension());
                if (!in_array($ext, $allowedMimes)) {
                    $fail('The :attribute must be a file of type: ' . implode(', ', array_map('strtoupper', $allowedMimes)) . '.');
                }
            },
        ];
    }
}

if (!function_exists('get_upload_config_display')) {
    /**
     * Get human-readable upload configuration for display
     * Example: "Max: 2 MB | Types: JPG, JPEG, PNG, PDF"
     * 
     * @param string|null $fieldName Field name from config/file_upload.php
     * @return string
     */
    function get_upload_config_display(?string $fieldName = null): string
    {
        $maxSizeKB = get_max_upload_size($fieldName);
        $maxSizeMB = $maxSizeKB / 1024;
        $mimes = strtoupper(str_replace(',', ', ', get_allowed_mimes($fieldName)));
        
        return "Max: {$maxSizeMB} MB | Types: {$mimes}";
    }
}
