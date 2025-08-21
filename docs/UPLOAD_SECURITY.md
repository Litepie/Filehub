# FileHub Upload Security Examples

## 1. Basic API Key Setup

Add to your `.env` file:
```env
FILEHUB_UPLOAD_API_KEY=your-secret-api-key-here
FILEHUB_REQUIRE_UPLOAD_TOKEN=true
FILEHUB_UPLOAD_TOKEN_EXPIRY=3600
FILEHUB_MAX_UPLOADS_PER_MINUTE=10
FILEHUB_MAX_UPLOADS_PER_HOUR=100
```

## 2. Generate Upload Token (Server-side)

```php
use Litepie\FileHub\Facades\UploadToken;

// Generate a token for uploading to a specific collection
$token = UploadToken::generateToken([
    'collection' => 'profile_images',
    'max_files' => 1,
    'allowed_mimes' => ['image/jpeg', 'image/png'],
    'max_size' => 2048, // KB
    'expires_in' => 1800, // 30 minutes
]);

return response()->json([
    'upload_token' => $token,
    'upload_url' => route('filehub.upload')
]);
```

## 3. Generate Upload Token via API

```bash
curl -X POST "https://yourapp.com/filehub/api/upload/token" \
  -H "X-FileHub-API-Key: your-secret-api-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "collection": "documents",
    "max_files": 5,
    "allowed_mimes": ["application/pdf", "image/jpeg"],
    "max_size": 10240,
    "expires_in": 3600
  }'
```

## 4. Upload Files with Token

```javascript
// Frontend JavaScript example
async function uploadFiles(files, modelType, modelId) {
    const formData = new FormData();
    
    // Add files
    files.forEach(file => {
        formData.append('files[]', file);
    });
    
    // Add model information
    formData.append('model_type', modelType);
    formData.append('model_id', modelId);
    formData.append('collection', 'gallery');
    
    const response = await fetch('/filehub/api/upload', {
        method: 'POST',
        headers: {
            'X-Upload-Token': 'your-upload-token-here'
        },
        body: formData
    });
    
    return response.json();
}
```

## 5. Upload with Direct API Key

```bash
curl -X POST "https://yourapp.com/filehub/api/upload" \
  -H "X-FileHub-API-Key: your-secret-api-key-here" \
  -F "files[]=@/path/to/file.jpg" \
  -F "model_type=App\Models\Product" \
  -F "model_id=123" \
  -F "collection=images"
```

## 6. Generate Signed Upload URL

```php
use Litepie\FileHub\Facades\UploadToken;

// Generate a signed URL (no token needed, URL contains signature)
$signedUrl = UploadToken::generateSignedUploadUrl([
    'model_type' => 'App\Models\Product',
    'model_id' => 123,
    'collection' => 'images',
    'max_files' => 3
]);

return response()->json([
    'upload_url' => $signedUrl
]);
```

## 7. Laravel Blade Component Usage

```blade
<x-filehub-upload 
    :upload-token="$uploadToken"
    collection="gallery"
    :multiple="true"
    accept="image/*"
    :max-size="5120"
    :preview="true"
/>
```

## 8. Model Integration

```php
use Litepie\FileHub\Traits\HasFileAttachments;

class Product extends Model
{
    use HasFileAttachments;
    
    public function generateUploadToken(string $collection = 'images'): string
    {
        return UploadToken::generateToken([
            'collection' => $collection,
            'max_files' => 10,
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size' => 5120,
            'expires_in' => 3600,
        ]);
    }
}
```

## 9. Security Benefits

### ✅ **API Key Protection**
- Prevents unauthorized uploads
- Server-side validation
- Can be rotated regularly

### ✅ **Upload Tokens**
- Time-limited access
- Specific permissions per token
- IP address validation
- Single-use options

### ✅ **Rate Limiting**
- Per-minute upload limits
- Per-hour upload limits
- IP-based throttling

### ✅ **Signed URLs**
- No token storage needed
- Built-in expiration
- Tamper-proof parameters

### ✅ **File Validation**
- MIME type restrictions
- File size limits
- Content validation
- Malware scanning options

## 10. Best Practices

1. **Always use HTTPS** for upload endpoints
2. **Rotate API keys** regularly
3. **Set appropriate token expiration** times
4. **Validate file types** on both client and server
5. **Implement proper error handling**
6. **Monitor upload patterns** for abuse
7. **Use CSP headers** to prevent XSS
8. **Log security events** for auditing

## 11. Error Handling

```php
try {
    $attachments = $model->attachFile($files, 'gallery');
} catch (SecurityException $e) {
    // Handle security violations
    return response()->json(['error' => 'Security violation'], 403);
} catch (ValidationException $e) {
    // Handle validation errors
    return response()->json(['error' => $e->getMessage()], 422);
} catch (FileHubException $e) {
    // Handle file upload errors
    return response()->json(['error' => 'Upload failed'], 500);
}
```
