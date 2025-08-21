# FileHub Components Installation & Usage Guide

This guide will walk you through installing and using the FileHub Vue.js and React components in your project.

## Prerequisites

Before you begin, ensure you have:

- Laravel project with FileHub package installed
- Node.js 16+ and npm/yarn
- Vue 3+ or React 18+ project setup

## Backend Setup

### 1. Install FileHub Package

```bash
composer require litepie/filehub
```

### 2. Publish and Run Migrations

```bash
php artisan vendor:publish --tag=filehub-migrations
php artisan vendor:publish --tag=filehub-config
php artisan migrate
```

### 3. Create Controller

Copy the example controller to your project:

```bash
cp vendor/litepie/filehub/examples/FileHubController.php app/Http/Controllers/
```

Or create your own based on the example.

### 4. Add Routes

Add these routes to your `routes/api.php`:

```php
use App\Http\Controllers\FileHubController;

Route::middleware(['auth:sanctum'])->prefix('api/filehub')->group(function () {
    Route::post('/token', [FileHubController::class, 'generateToken']);
    Route::post('/upload', [FileHubController::class, 'upload']);
    Route::put('/files/{id}/metadata', [FileHubController::class, 'updateMetadata']);
    Route::delete('/files/{id}', [FileHubController::class, 'delete']);
    Route::post('/files/reorder', [FileHubController::class, 'reorder']);
    Route::get('/files', [FileHubController::class, 'list']);
});
```

## Frontend Setup

### For Vue.js Projects

#### 1. Install Dependencies

```bash
npm install vuedraggable axios
```

#### 2. Copy Component Files

Copy the Vue component to your project:

```bash
# Create components directory if it doesn't exist
mkdir -p resources/js/components

# Copy the Vue component
cp vendor/litepie/filehub/resources/js/components/FileHubUploader.vue resources/js/components/
```

#### 3. Register Component

In your `resources/js/app.js`:

```js
import { createApp } from 'vue'
import FileHubUploader from './components/FileHubUploader.vue'

const app = createApp({})
app.component('FileHubUploader', FileHubUploader)
app.mount('#app')
```

#### 4. Use in Templates

```vue
<template>
  <div>
    <FileHubUploader
      :upload-token="uploadToken"
      collection="gallery"
      :multiple="true"
      @upload-success="handleSuccess"
      @upload-error="handleError"
    />
  </div>
</template>

<script>
export default {
  data() {
    return {
      uploadToken: null
    }
  },
  async mounted() {
    await this.generateToken()
  },
  methods: {
    async generateToken() {
      const response = await axios.post('/api/filehub/token', {
        collection: 'gallery'
      })
      this.uploadToken = response.data.token
    },
    handleSuccess(files) {
      console.log('Upload successful:', files)
    },
    handleError(errors) {
      console.error('Upload failed:', errors)
    }
  }
}
</script>
```

### For React Projects

#### 1. Install Dependencies

```bash
npm install axios
```

#### 2. Copy Component Files

```bash
# Create components directory if it doesn't exist
mkdir -p src/components

# Copy the React component
cp vendor/litepie/filehub/resources/js/components/FileHubUploader.jsx src/components/
cp vendor/litepie/filehub/resources/js/components/FileHubUploader.css src/components/
```

#### 3. Use in Components

```jsx
import React, { useState, useEffect } from 'react';
import FileHubUploader from './components/FileHubUploader';
import axios from 'axios';

const App = () => {
  const [uploadToken, setUploadToken] = useState(null);

  useEffect(() => {
    generateToken();
  }, []);

  const generateToken = async () => {
    try {
      const response = await axios.post('/api/filehub/token', {
        collection: 'gallery'
      });
      setUploadToken(response.data.token);
    } catch (error) {
      console.error('Failed to generate token:', error);
    }
  };

  const handleSuccess = (files) => {
    console.log('Upload successful:', files);
  };

  const handleError = (errors) => {
    console.error('Upload failed:', errors);
  };

  return (
    <div>
      {uploadToken && (
        <FileHubUploader
          uploadToken={uploadToken}
          collection="gallery"
          multiple={true}
          onUploadSuccess={handleSuccess}
          onUploadError={handleError}
        />
      )}
    </div>
  );
};

export default App;
```

## Configuration Options

### Environment Variables

Add these to your `.env` file:

```env
FILEHUB_DISK=public
FILEHUB_MAX_SIZE=10240
FILEHUB_MAX_FILES=10
FILEHUB_QUEUE_ENABLED=true
FILEHUB_REQUIRE_UPLOAD_TOKEN=true
FILEHUB_UPLOAD_TOKEN_EXPIRY=3600
```

### Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `uploadToken` | String | Required | Security token from backend |
| `collection` | String | 'default' | File collection name |
| `multiple` | Boolean | true | Allow multiple files |
| `accept` | String | 'image/*,video/*...' | Accepted file types |
| `maxSize` | Number | 10240 | Max file size in KB |
| `maxFiles` | Number | 10 | Max number of files |
| `showCaption` | Boolean | true | Show caption field |

## Advanced Usage

### Custom File Types

```js
// Images only
accept="image/*"

// Documents only  
accept=".pdf,.doc,.docx,.txt"

// Specific image formats
accept=".jpg,.jpeg,.png,.gif"
```

### File Size Limits

```js
// 5MB limit
maxSize={5120}

// 50MB limit for videos
maxSize={51200}
```

### Custom Styling

```css
/* Override default styles */
.filehub-uploader .upload-zone {
  border: 3px dashed #your-color;
  background: #your-background;
}

.filehub-uploader .btn-primary {
  background: #your-brand-color;
}
```

### Error Handling

```js
// Vue.js
methods: {
  handleError(errors) {
    if (typeof errors === 'string') {
      this.$toast.error(errors)
    } else {
      errors.forEach(error => {
        this.$toast.error(`${error.file.name}: ${error.error}`)
      })
    }
  }
}

// React
const handleError = (errors) => {
  if (typeof errors === 'string') {
    toast.error(errors)
  } else {
    errors.forEach(error => {
      toast.error(`${error.file.name}: ${error.error}`)
    })
  }
}
```

## Security Considerations

1. **Always validate upload tokens** on the backend
2. **Set appropriate file size limits** to prevent abuse
3. **Validate file types** both client and server-side
4. **Use middleware** to protect upload endpoints
5. **Implement rate limiting** for upload requests

## Troubleshooting

### Common Issues

#### "Invalid upload token"
- Ensure token is generated before component mounts
- Check token expiry time (default 1 hour)
- Verify API endpoint is correct

#### Files not uploading
- Check browser console for JavaScript errors
- Verify CSRF token if using web routes
- Ensure file size doesn't exceed limits

#### Drag and drop not working
- Check browser compatibility
- Ensure proper event handlers are attached
- Verify component is properly mounted

### Debug Mode

Enable debug logging in your component:

```js
// Vue.js
methods: {
  handleFilesChanged(files) {
    console.log('Files changed:', files)
  },
  handleUploadProgress(data) {
    console.log('Upload progress:', data)
  }
}

// React
const handleFilesChanged = (files) => {
  console.log('Files changed:', files)
}

const handleUploadProgress = (data) => {
  console.log('Upload progress:', data)
}
```

## Production Deployment

### Build Assets

```bash
# Vue.js with Vite
npm run build

# React
npm run build
```

### Server Configuration

Ensure your server can handle file uploads:

```nginx
# Nginx
client_max_body_size 50M;
```

```apache
# Apache
LimitRequestBody 52428800
```

### Queue Configuration

For production, enable queues for image processing:

```env
FILEHUB_QUEUE_ENABLED=true
QUEUE_CONNECTION=redis
```

Run queue workers:

```bash
php artisan queue:work --queue=filehub
```

## Next Steps

- Explore the [FileHub documentation](FRONTEND_COMPONENTS.md) for advanced features
- Check out the [demo page](../resources/js/demo.html) for interactive examples
- Consider implementing [custom validation](../docs/UPLOAD_SECURITY.md) for your use case
