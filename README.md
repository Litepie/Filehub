# Laravel FileHub

A modern, secure, and feature-rich file management package for Laravel 11+ applications.

## Features

- 🔒 **Security First**: Comprehensive file validation, malware scanning, and security checks
- 🚀 **Performance**: Queue-based image processing and optimized storage
- 🎨 **Image Processing**: Automatic variant generation with Intervention Image v3
- ☁️ **Multi-Storage**: Support for local, S3, and any Laravel filesystem
- 🔧 **Flexible**: Trait-based integration with Eloquent models
- 📦 **Modern**: Laravel 11+ and PHP 8.2+ with full type safety
- 🧹 **Clean**: Automatic orphaned file cleanup and management
- 👤 **User Tracking**: Track who uploaded files, when, and from which IP address

## Installation

```bash
composer require litepie/filehub
```

## Quick Start

1. **Publish and run migrations:**
```bash
php artisan vendor:publish --tag=filehub-migrations
php artisan migrate
```

2. **Add trait to your model:**
```php
use Litepie\FileHub\Traits\HasFileAttachments;

class Product extends Model
{
    use HasFileAttachments;
}
```

3. **Upload files:**
```php
$product = Product::create($data);
$product->attachFile($request->file('image'), 'gallery');
```

4. **Display files:**
```php
$image = $product->getFirstFileAttachment('gallery');
echo $image->url(); // Full size
echo $image->getVariantUrl('thumbnail'); // Thumbnail
```

## User Tracking

Track who uploaded files with comprehensive user tracking:

```php
// Check who uploaded a file
$attachment = FileAttachment::find(1);
if ($attachment->hasUploader()) {
    echo "Uploaded by: " . $attachment->uploader_name;
    echo "IP Address: " . $attachment->upload_ip_address;
    echo "Upload Date: " . $attachment->created_at;
}

// Get all files uploaded by a user
use Litepie\FileHub\Facades\FileUploader;

$files = FileUploader::getFilesByUploader(Auth::user());
$stats = FileUploader::getUploaderStats(Auth::user());

// Admin functions
$allUploaders = FileUploader::getAllUploaders();
$recentUploads = FileUploader::getRecentUploads(50);
$duplicates = FileUploader::findPotentialDuplicates();
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=filehub-config
```

## Frontend Components

FileHub now includes beautiful Vue.js and React components for file uploading with advanced features:

- 🎨 **Modern UI**: Beautiful, responsive design with smooth animations
- 📁 **Drag & Drop**: Native drag-and-drop support with visual feedback
- 🔄 **File Reordering**: Drag files to reorder them before upload
- ✏️ **Inline Editing**: Edit file titles and captions directly
- 📊 **Progress Tracking**: Real-time upload progress indicators
- 🖼️ **Image Previews**: Automatic thumbnail generation

### Quick Start with Components

```bash
# Copy components to your project
cp -r vendor/litepie/filehub/resources/js/components/ resources/js/
```

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

For full documentation, visit [our documentation site](https://github.com/litepie/filehub).

- [Frontend Components Guide](docs/FRONTEND_COMPONENTS.md) - Vue & React components
- [Installation Guide](docs/INSTALLATION_GUIDE.md) - Step-by-step setup
- [User Tracking Guide](docs/USER_TRACKING.md) - Track file uploads
- [Upload Security](docs/UPLOAD_SECURITY.md) - Security features

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
