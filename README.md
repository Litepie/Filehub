# FileHub - Laravel File Management Package

A comprehensive file upload and management package for Laravel applications with support for polymorphic attachments, image processing, variant generation, user tracking, and document metadata.

## Features

- 📎 **Polymorphic File Attachments** - Attach files to any Eloquent model
- 🖼️ **Image Processing** - Automatic variant generation (thumbnails, different sizes)
- 🔒 **Security** - File validation, malware scanning, signature verification
- 📊 **User Tracking** - Track who uploaded what, when, and from where
- 📄 **Document Metadata** - Store titles, descriptions, document numbers, dates
- 🎯 **Collections** - Organize files into collections (avatars, documents, galleries)
- ⚡ **Queue Support** - Process image variants in background
- 🔐 **Upload Tokens** - Secure upload endpoints with temporary tokens
- 🌐 **URL Generation** - Signed URLs, temporary URLs, variant URLs
- 🗑️ **Soft Deletes** - Safe file deletion with recovery options

## Installation

### 1. Install via Composer

```bash
composer require litepie/filehub
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Litepie\FileHub\Providers\FileHubServiceProvider"
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Storage

Make sure your storage is properly configured in `config/filesystems.php`:

```php
'disks' => [
    'public' => [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => env('APP_URL').'/storage',
        'visibility' => 'public',
    ],
],
```

Link storage:
```bash
php artisan storage:link
```

## Configuration

The configuration file is published to `config/filehub.php`. Key settings include:

```php
return [
    // Default storage disk
    'default_disk' => env('FILEHUB_DISK', 'public'),
    
    // Validation rules
    'validation' => [
        'max_size' => env('FILEHUB_MAX_SIZE', 10240), // KB
        'allowed_mimes' => [...],
        'forbidden_extensions' => ['exe', 'bat', ...],
    ],
    
    // Image processing
    'image_processing' => [
        'driver' => env('FILEHUB_IMAGE_DRIVER', 'gd'),
        'quality' => 85,
        'variants' => [
            'thumbnail' => ['width' => 150, 'height' => 150, 'method' => 'crop'],
            'small' => ['width' => 300, 'height' => 300],
            'medium' => ['width' => 600, 'height' => 600],
            'large' => ['width' => 1200, 'height' => 1200],
        ],
    ],
    
    // Security
    'security' => [
        'hash_filenames' => true,
        'file_signature_check' => true,
        'require_upload_token' => true,
    ],
];
```

## Basic Usage

### Add Trait to Model

```php
use Litepie\FileHub\Traits\HasFileAttachments;

class Organization extends Model
{
    use HasFileAttachments;
}
```

### Upload Files

```php
// Single file upload
$organization = Organization::find(1);
$attachment = $organization->attachFile($request->file('logo'), 'logos');

// Multiple files
$attachments = $organization->attachFile($request->file('documents'), 'documents');

// With metadata
$attachment = $organization->attachFile($uploadedFile, 'licenses', [
    'document_type' => 'trade_license',
    'title' => 'Trade License',
    'description' => 'Company trade license document',
    'document_number' => 'TL-2025-001',
    'issue_date' => '2025-01-01',
    'expiry_date' => '2026-01-01',
]);
```

### Upload from URL or Path

```php
// From URL
$attachment = $organization->attachFromUrl(
    'https://example.com/document.pdf',
    'documents'
);

// From local path
$attachment = $organization->attachFromPath(
    storage_path('temp/file.pdf'),
    'documents'
);
```

### Retrieve Files

```php
// Get all attachments
$files = $organization->fileAttachments;

// Get by collection
$logos = $organization->getFileAttachments('logos');
$documents = $organization->getFileAttachments('documents');

// Get first/latest
$logo = $organization->getFirstFileAttachment('logos');
$latest = $organization->getLatestFileAttachment('documents');

// Check if has attachments
if ($organization->hasFileAttachments('logos')) {
    // ...
}

// Count attachments
$count = $organization->getFileAttachmentsCount('documents');
```

### Access File URLs

```php
$attachment = FileAttachment::find(1);

// Direct access (property)
echo $attachment->url;
echo $attachment->download_url;

// Method call with variant support
echo $attachment->url(); // Original
echo $attachment->url('thumbnail'); // Thumbnail variant
echo $attachment->url('medium'); // Medium variant

// Signed URLs (temporary)
$signedUrl = $attachment->getSignedUrl(3600); // 1 hour

// Variant URLs
$thumbnailUrl = $attachment->getVariantUrl('thumbnail');
```

### Delete Files

```php
// Delete single file
$organization->detachFile($attachmentId);

// Delete all files in a collection
$organization->detachAllFiles('documents');

// Delete all files
$organization->detachAllFiles();
```

## Document Metadata

FileHub supports rich document metadata:

```php
$attachment = FileAttachment::create([
    'document_type' => 'letterhead',      // Document category
    'title' => 'Company Letterhead',      // Display title
    'description' => 'Official letterhead', // Detailed description
    'document_number' => 'LH-2025-001',   // Reference number
    'issue_date' => '2025-01-01',         // Issue/creation date
    'expiry_date' => '2026-01-01',        // Expiration date
]);

// Query by metadata
$licenses = FileAttachment::where('document_type', 'license')->get();
$expiringSoon = FileAttachment::where('expiry_date', '<=', now()->addMonth())->get();
```

## Image Processing

### Automatic Variants

Images are automatically processed into multiple sizes:

```php
$organization->attachFile($imageFile, 'gallery');

// Access variants
$attachment = FileAttachment::latest()->first();
echo $attachment->url('thumbnail'); // 150x150
echo $attachment->url('small');     // 300x300
echo $attachment->url('medium');    // 600x600
echo $attachment->url('large');     // 1200x1200

// Check available variants
$variants = $attachment->getVariants();
```

### Regenerate Variants

```php
// Single attachment
$organization->regenerateFileVariants($attachmentId);

// Command line
php artisan filehub:regenerate-variants
php artisan filehub:regenerate-variants --collection=gallery
php artisan filehub:regenerate-variants --queue
```

## API Usage

### Generate Upload Token

```php
POST /api/filehub/token

{
    "collection": "documents",
    "max_files": 10,
    "allowed_mimes": ["application/pdf", "image/jpeg"],
    "max_size": 5120,
    "expires_in": 3600
}

Response:
{
    "upload_token": "abc123...",
    "expires_at": "2025-12-05T12:00:00Z",
    "upload_url": "/api/filehub/upload"
}
```

### Upload Files via API

```php
POST /api/filehub/upload

Headers:
X-Upload-Token: abc123...
// OR
X-FileHub-API-Key: your-api-key

Body (multipart/form-data):
{
    "files[]": (file),
    "model_type": "App\\Models\\Organization",
    "model_id": "1",
    "collection": "documents",
    "document_type": "license",
    "title": "Trade License",
    "description": "Company trade license",
    "document_number": "TL-2025-001",
    "issue_date": "2025-01-01",
    "expiry_date": "2026-01-01"
}

Response:
{
    "success": true,
    "message": "Files uploaded successfully",
    "attachments": [
        {
            "id": 1,
            "title": "Trade License",
            "description": "Company trade license",
            "document_type": "license",
            "document_number": "TL-2025-001",
            "issue_date": "2025-01-01",
            "expiry_date": "2026-01-01",
            "file_name": "license.pdf",
            "file_size": 46300,
            "file_size_human": "45.21 KB",
            "mime_type": "application/pdf",
            "url": "http://localhost/storage/...",
            "uploaded_at": "2025-12-05T10:30:00+00:00",
            "variants": ["thumbnail", "small", "medium", "large"]
        }
    ]
}
```

## User Tracking

FileHub automatically tracks upload metadata:

```php
$attachment = FileAttachment::find(1);

// Who uploaded
$uploader = $attachment->uploadedBy; // User model
$uploaderName = $attachment->uploader_name;

// Upload details
$ipAddress = $attachment->upload_ip_address;
$userAgent = $attachment->upload_user_agent;
$uploadedAt = $attachment->uploaded_at;

// Query by uploader
$userFiles = FileAttachment::uploadedBy($user)->get();
$ipFiles = FileAttachment::uploadedFromIp('192.168.1.1')->get();

// Get upload statistics
use Litepie\FileHub\Facades\FileUploader;

$stats = FileUploader::getUploaderStats($user);
$recent = FileUploader::getRecentUploads(50);
$duplicates = FileUploader::findPotentialDuplicates();
```

## Security Features

- ✅ File size limits
- ✅ MIME type whitelist
- ✅ File extension blacklist
- ✅ File signature verification
- ✅ Embedded script detection
- ✅ Upload token authentication
- ✅ API key authentication

## Cleanup Commands

```bash
php artisan filehub:cleanup-orphaned --dry-run
php artisan filehub:cleanup-orphaned --days=7
```

## Frontend Components

FileHub includes beautiful Vue.js, React, and React Native components:

```vue
<!-- Vue.js -->
<FileHubUploader
  :upload-token="uploadToken"
  collection="gallery"
  :multiple="true"
  @upload-success="handleSuccess"
/>
```

```jsx
// React
<FileHubUploader
  uploadToken={uploadToken}
  collection="gallery"
  multiple={true}
  onUploadSuccess={handleSuccess}
/>
```

## Documentation

- [Frontend Components Guide](docs/FRONTEND_COMPONENTS.md)
- [Installation Guide](docs/INSTALLATION_GUIDE.md)
- [User Tracking Guide](docs/USER_TRACKING.md)
- [Upload Security](docs/UPLOAD_SECURITY.md)
- [Migration Guide](MIGRATION_GUIDE.md)

## Testing

```php
use Illuminate\Http\UploadedFile;

public function test_file_upload()
{
    $organization = Organization::factory()->create();
    $file = UploadedFile::fake()->image('logo.jpg');
    
    $attachment = $organization->attachFile($file, 'logos');
    
    $this->assertInstanceOf(FileAttachment::class, $attachment);
    $this->assertEquals('logos', $attachment->collection);
    $this->assertTrue($attachment->isImage());
}
```

## Troubleshooting

### Images Not Processing
- Check GD/Imagick: `php -m | grep -E 'gd|imagick'`
- Verify storage permissions: `chmod -R 775 storage`
- Check queue: `php artisan queue:work`

### Upload Fails
- Check PHP limits: `upload_max_filesize`, `post_max_size`
- Verify storage disk is writable
- Check validation rules in config

### Variants Not Generating
- Run manually: `php artisan filehub:regenerate-variants`
- Check logs: `storage/logs/laravel.log`

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## 🏢 About

This package is part of the **Litepie** ecosystem, developed by **Renfos Technologies**. 

### Organization Structure
- **Vendor:** Litepie
- **Framework:** Lavalite
- **Company:** Renfos Technologies

### Links & Resources
- 🌐 **Website:** [https://lavalite.org](https://lavalite.org)
- 📚 **Documentation:** [https://docs.lavalite.org](https://docs.lavalite.org)
- 💼 **Company:** [https://renfos.com](https://renfos.com)
- 📧 **Support:** [support@lavalite.org](mailto:support@lavalite.org)

---

<div align="center">
  <p><strong>Built with ❤️ by Renfos Technologies</strong></p>
  <p><em>Empowering developers with robust Laravel solutions</em></p>
</div>
