<?php

use App\Models\SystemConfiguration;

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
     * Get maximum upload size in KB from system configuration
     * Default: 20 MB (20480 KB)
     */
    function get_max_upload_size(): int
    {
        $maxSizeMB = SystemConfiguration::get('file.max_upload_size', 20);
        return (int) $maxSizeMB * 1024; // Convert MB to KB
    }
}

if (!function_exists('get_allowed_mimes')) {
    /**
     * Get allowed MIME types from system configuration
     * Returns comma-separated string
     * Default: pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,gif,bmp,svg
     */
    function get_allowed_mimes(): string
    {
        return SystemConfiguration::get('file.allowed_mimes', 'pdf,doc,docx,xls,xlsx,png,jpg,jpeg,webp,gif,bmp,svg');
    }
}

if (!function_exists('get_allowed_mimes_array')) {
    /**
     * Get allowed MIME types as array
     */
    function get_allowed_mimes_array(): array
    {
        $mimes = get_allowed_mimes();
        return array_map('trim', explode(',', $mimes));
    }
}

if (!function_exists('file_upload_validation_rule')) {
    /**
     * Get complete file upload validation rule string
     * Example: "nullable|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg"
     * 
     * @param bool $required Whether file is required
     * @return string
     */
    function file_upload_validation_rule(bool $required = false): string
    {
        $rules = [];
        
        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = 'file';
        $rules[] = 'max:' . get_max_upload_size();
        $rules[] = 'mimes:' . get_allowed_mimes();
        
        return implode('|', $rules);
    }
}

if (!function_exists('get_upload_config_display')) {
    /**
     * Get human-readable upload configuration for display
     * Example: "Max: 20 MB | Types: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG"
     */
    function get_upload_config_display(): string
    {
        $maxSizeMB = SystemConfiguration::get('file.max_upload_size', 20);
        $mimes = strtoupper(str_replace(',', ', ', get_allowed_mimes()));
        
        return "Max: {$maxSizeMB} MB | Types: {$mimes}";
    }
}
