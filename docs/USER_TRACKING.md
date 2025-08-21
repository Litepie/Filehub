# FileHub User Tracking Documentation

FileHub now includes comprehensive user tracking functionality to help you monitor who uploaded files, when they were uploaded, and from which IP addresses.

## Features

- **User Attribution**: Track which user uploaded each file
- **IP Address Logging**: Record the IP address of file uploads
- **User Agent Tracking**: Store browser/client information
- **Upload Statistics**: Generate detailed reports on user activity
- **Query Methods**: Find files by uploader, IP address, or other criteria

## Database Schema

The user tracking functionality adds the following fields to the `file_attachments` table:

```php
$table->unsignedBigInteger('uploaded_by')->nullable();
$table->string('uploaded_by_type')->nullable();
$table->string('upload_ip_address', 45)->nullable();
$table->text('upload_user_agent')->nullable();
```

## Model Relationships

### FileAttachment Model

The `FileAttachment` model now includes:

```php
// Polymorphic relationship to the user who uploaded the file
public function uploadedBy(): MorphTo
{
    return $this->morphTo('uploaded_by');
}

// Get uploader name (tries multiple name attributes)
public function getUploaderNameAttribute(): ?string
{
    if (!$this->uploadedBy) {
        return null;
    }
    
    // Tries: name, display_name, full_name, username, email
    foreach (['name', 'display_name', 'full_name', 'username', 'email'] as $attribute) {
        if (isset($this->uploadedBy->$attribute)) {
            return $this->uploadedBy->$attribute;
        }
    }
    
    return "User #{$this->uploaded_by}";
}

// Check if file has an uploader
public function hasUploader(): bool
{
    return !is_null($this->uploaded_by) && !is_null($this->uploaded_by_type);
}
```

### Query Scopes

New query scopes for finding files by uploader:

```php
// Find files uploaded by specific user
FileAttachment::uploadedBy($user)->get();

// Find files uploaded by user type
FileAttachment::uploadedByType('App\\Models\\User')->get();

// Find files uploaded from specific IP
FileAttachment::uploadedFromIp('192.168.1.1')->get();
```

## Usage Examples

### 1. Basic Usage - Check Who Uploaded a File

```php
$attachment = FileAttachment::find(1);

if ($attachment->hasUploader()) {
    echo "Uploaded by: " . $attachment->uploader_name;
    echo "IP Address: " . $attachment->upload_ip_address;
    echo "Upload Date: " . $attachment->created_at;
}
```

### 2. Get All Files Uploaded by a User

```php
use Litepie\FileHub\Facades\FileUploader;

$user = Auth::user();

// Get all files uploaded by the user
$files = FileUploader::getFilesByUploader($user);

// Get files with filters
$files = FileUploader::getFilesByUploader($user, [
    'collection' => 'profile_images',
    'file_type' => 'image',
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'order_by' => 'created_at',
    'order_direction' => 'desc',
    'paginate' => 15
]);
```

### 3. User Upload Statistics

```php
use Litepie\FileHub\Facades\FileUploader;

$user = Auth::user();
$stats = FileUploader::getUploaderStats($user);

/*
Returns:
[
    'total_files' => 150,
    'total_size' => 52428800, // bytes
    'files_by_type' => [
        ['mime_type' => 'image/jpeg', 'count' => 45, 'total_size' => 15728640, 'file_type' => 'image'],
        ['mime_type' => 'application/pdf', 'count' => 20, 'total_size' => 10485760, 'file_type' => 'document'],
        // ...
    ],
    'files_by_collection' => [
        ['collection' => 'profile_images', 'count' => 5],
        ['collection' => 'documents', 'count' => 25],
        // ...
    ],
    'upload_timeline' => [
        ['date' => '2024-08-21', 'count' => 3],
        ['date' => '2024-08-20', 'count' => 7],
        // ...
    ]
]
*/
```

### 4. Admin Functions - Get All Uploaders

```php
use Litepie\FileHub\Facades\FileUploader;

// Get all users who have uploaded files
$uploaders = FileUploader::getAllUploaders();

foreach ($uploaders as $uploaderInfo) {
    echo "User: " . $uploaderInfo['user']->name;
    echo "Total Files: " . $uploaderInfo['file_count'];
    echo "Total Size: " . $uploaderInfo['total_size'];
    echo "Last Upload: " . $uploaderInfo['last_upload'];
}
```

### 5. Upload Activity Report

```php
use Litepie\FileHub\Facades\FileUploader;

$activity = FileUploader::getUploadActivity('2024-08-01', '2024-08-31');

/*
Returns:
[
    'total_uploads' => 450,
    'unique_uploaders' => 25,
    'total_size' => 157286400,
    'uploads_by_day' => [
        '2024-08-21' => 15,
        '2024-08-20' => 23,
        // ...
    ],
    'uploads_by_user' => [
        'user_id' => [
            'user' => User{...},
            'count' => 45,
            'total_size' => 15728640
        ],
        // ...
    ]
]
*/
```

### 6. Find Files from Specific IP Address

```php
use Litepie\FileHub\Facades\FileUploader;

$files = FileUploader::getFilesByIpAddress('192.168.1.100');

foreach ($files as $file) {
    echo "File: " . $file->original_filename;
    echo "Uploaded by: " . $file->uploader_name;
    echo "Date: " . $file->created_at;
}
```

### 7. User Quota Management

```php
use Litepie\FileHub\Facades\FileUploader;

$user = Auth::user();

$quotaInfo = FileUploader::getUserQuotaInfo($user, [
    'max_size' => 100 * 1024 * 1024, // 100MB
    'max_files' => 1000
]);

/*
Returns:
[
    'used_size' => 52428800,
    'used_files' => 150,
    'max_size' => 104857600,
    'max_files' => 1000,
    'size_percentage' => 50.0,
    'files_percentage' => 15.0,
    'can_upload' => true
]
*/

if (!$quotaInfo['can_upload']) {
    return response()->json(['error' => 'Upload quota exceeded'], 403);
}
```

### 8. Find Potential Duplicate Files

```php
use Litepie\FileHub\Facades\FileUploader;

$duplicates = FileUploader::findPotentialDuplicates();

foreach ($duplicates as $duplicate) {
    echo "File Hash: " . $duplicate['file_hash'];
    echo "Duplicate Count: " . $duplicate['duplicate_count'];
    
    foreach ($duplicate['files'] as $file) {
        echo "  - {$file->original_filename} uploaded by {$file->uploader_name}";
    }
}
```

## API Endpoints

If you want to expose user tracking functionality via API, you can use the provided `FileUploaderController`:

```php
// In your routes file
Route::middleware(['auth:api'])->group(function () {
    Route::get('/my-uploads', [FileUploaderController::class, 'myUploads']);
    Route::get('/my-stats', [FileUploaderController::class, 'myStats']);
    Route::get('/my-quota', [FileUploaderController::class, 'quota']);
});

// Admin routes
Route::middleware(['auth:api', 'admin'])->group(function () {
    Route::get('/users/{userId}/uploads', [FileUploaderController::class, 'userUploads']);
    Route::get('/uploaders', [FileUploaderController::class, 'uploaders']);
    Route::get('/recent-uploads', [FileUploaderController::class, 'recentUploads']);
    Route::get('/upload-activity', [FileUploaderController::class, 'uploadActivity']);
    Route::get('/ip/{ipAddress}/files', [FileUploaderController::class, 'filesByIp']);
    Route::get('/duplicates', [FileUploaderController::class, 'duplicates']);
});
```

## Response Format

When files are uploaded, the response now includes uploader information:

```json
{
  "success": true,
  "message": "Files uploaded successfully",
  "attachments": [
    {
      "id": 123,
      "filename": "image_123456.jpg",
      "original_filename": "profile.jpg",
      "mime_type": "image/jpeg",
      "size": 1048576,
      "human_readable_size": "1.00 MB",
      "file_type": "image",
      "collection": "profile_images",
      "url": "/filehub/file/123",
      "download_url": "/filehub/file/123/download",
      "variants": ["thumbnail", "small", "medium"],
      "created_at": "2024-08-21T10:30:00Z",
      "uploader": {
        "name": "John Doe",
        "ip_address": "192.168.1.100",
        "uploaded_at": "2024-08-21T10:30:00Z"
      }
    }
  ]
}
```

## Security Considerations

1. **IP Address Privacy**: Be mindful of privacy laws when storing and displaying IP addresses
2. **Access Control**: Implement proper authorization for viewing other users' upload information
3. **Data Retention**: Consider implementing data retention policies for tracking information
4. **Audit Trails**: Use this data for security auditing and compliance

## Configuration

You can control user tracking behavior in your `config/filehub.php`:

```php
return [
    // ... other config
    
    'user_tracking' => [
        'enabled' => env('FILEHUB_USER_TRACKING', true),
        'track_ip' => env('FILEHUB_TRACK_IP', true),
        'track_user_agent' => env('FILEHUB_TRACK_USER_AGENT', true),
        'anonymize_ip_after_days' => env('FILEHUB_ANONYMIZE_IP_DAYS', null), // Optional
    ],
];
```

## Migration

To add user tracking to existing installations:

```bash
php artisan vendor:publish --tag=filehub-migrations
php artisan migrate
```

The migration will add the new tracking fields without affecting existing data.
