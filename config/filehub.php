<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Storage Configuration
    |--------------------------------------------------------------------------
    */
    'default_disk' => env('FILEHUB_DISK', 'public'),
    'temp_disk' => env('FILEHUB_TEMP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | File Upload Validation
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'max_size' => env('FILEHUB_MAX_SIZE', 10240), // KB
        'max_files_per_request' => env('FILEHUB_MAX_FILES', 10),
        
        'allowed_mimes' => [
            // Images
            'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 
            'image/svg+xml', 'image/bmp', 'image/tiff',
            
            // Documents
            'application/pdf', 'text/plain', 'text/csv', 'application/json',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Media
            'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm',
            
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        ],
        
        'forbidden_extensions' => [
            'exe', 'bat', 'com', 'cmd', 'scr', 'pif', 'vbs', 'js', 'jar', 'ps1', 'sh'
        ],
        
        'scan_malware' => env('FILEHUB_SCAN_MALWARE', false),
        'check_image_contents' => env('FILEHUB_CHECK_IMAGE_CONTENTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Processing Configuration
    |--------------------------------------------------------------------------
    */
    'image_processing' => [
        'driver' => env('FILEHUB_IMAGE_DRIVER', 'gd'), // gd or imagick
        'quality' => env('FILEHUB_IMAGE_QUALITY', 85),
        'auto_orient' => true,
        'strip_metadata' => env('FILEHUB_STRIP_METADATA', true),
        
        'variants' => [
            'thumbnail' => [
                'width' => 150,
                'height' => 150,
                'method' => 'crop',
                'quality' => 80,
            ],
            'small' => [
                'width' => 300,
                'height' => 300,
                'method' => 'resize',
                'quality' => 85,
            ],
            'medium' => [
                'width' => 600,
                'height' => 600,
                'method' => 'resize',
                'quality' => 85,
            ],
            'large' => [
                'width' => 1200,
                'height' => 1200,
                'method' => 'resize',
                'quality' => 90,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */
    'queue' => [
        'enabled' => env('FILEHUB_QUEUE_ENABLED', false),
        'connection' => env('FILEHUB_QUEUE_CONNECTION', 'default'),
        'queue' => env('FILEHUB_QUEUE_NAME', 'filehub'),
        'timeout' => env('FILEHUB_QUEUE_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'secure_filenames' => true,
        'hash_filenames' => true,
        'preserve_original_name' => true,
        'virus_scan' => env('FILEHUB_VIRUS_SCAN', false),
        'content_type_validation' => true,
        'file_signature_check' => true,
        
        // Upload Security
        'upload_api_key' => env('FILEHUB_UPLOAD_API_KEY'),
        'require_upload_token' => env('FILEHUB_REQUIRE_UPLOAD_TOKEN', true),
        'upload_token_expiry' => env('FILEHUB_UPLOAD_TOKEN_EXPIRY', 3600), // seconds
        'max_uploads_per_minute' => env('FILEHUB_MAX_UPLOADS_PER_MINUTE', 10),
        'max_uploads_per_hour' => env('FILEHUB_MAX_UPLOADS_PER_HOUR', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        'orphaned_files_after_days' => 7,
        'temp_files_after_hours' => 24,
        'failed_uploads_after_hours' => 1,
        'auto_cleanup' => env('FILEHUB_AUTO_CLEANUP', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | URL Generation Settings
    |--------------------------------------------------------------------------
    */
    'urls' => [
        'signed' => env('FILEHUB_SIGNED_URLS', false),
        'expiration' => 3600, // seconds
        'route_prefix' => 'filehub',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Middleware Configuration
    |--------------------------------------------------------------------------
    */
    'middleware' => [
        // Base middleware for all FileHub routes
        'base' => ['api'],
        
        // Authentication middleware for user routes
        'auth' => ['api', 'auth'],
        
        // Admin middleware for admin routes
        'admin' => ['api', 'auth'], // Add your admin middleware here (e.g., 'admin', 'can:manage-files')
        
        // Upload middleware for file upload routes
        'upload' => ['api', 'auth', 'throttle:uploads'],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Organization
    |--------------------------------------------------------------------------
    */
    'organization' => [
        'directory_structure' => 'model/id/collection', // model/id/collection or date or custom
        'date_format' => 'Y/m/d',
        'max_files_per_directory' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'cache_file_info' => true,
        'cache_ttl' => 3600,
        'lazy_load_variants' => true,
        'chunk_size' => 8192,
    ],
];
