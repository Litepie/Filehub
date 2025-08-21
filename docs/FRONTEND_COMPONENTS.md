# FileHub Frontend Components

Beautiful, feature-rich Vue.js and React components for the FileHub Laravel package. These components provide drag-and-drop file uploading, reordering, title/caption editing, and real-time upload progress.

## Features

- 🎨 **Beautiful UI**: Modern, responsive design with smooth animations
- 📁 **Drag & Drop**: Native drag-and-drop support with visual feedback
- 🔄 **File Reordering**: Drag files to reorder them before upload
- ✏️ **Inline Editing**: Edit file titles and captions directly in the interface
- 📊 **Progress Tracking**: Real-time upload progress with visual indicators
- 🖼️ **Image Previews**: Automatic thumbnail generation for images
- 📱 **Responsive**: Works perfectly on desktop, tablet, and mobile
- ⚡ **Framework Agnostic**: Available for both Vue.js and React
- 🔒 **Secure**: Works with FileHub's security tokens and validation

## Installation

### For Vue.js Projects

```bash
npm install @litepie/filehub-components vuedraggable axios
```

### For React Projects

```bash
npm install @litepie/filehub-components axios
```

## Quick Start

### Vue.js Component

```vue
<template>
  <div>
    <FileHubUploader
      :upload-token="uploadToken"
      collection="gallery"
      :multiple="true"
      :show-caption="true"
      upload-text="Drop your images here"
      @upload-success="handleUploadSuccess"
      @upload-error="handleUploadError"
      @files-changed="handleFilesChanged"
    />
  </div>
</template>

<script>
import { FileHubUploader } from '@litepie/filehub-components'

export default {
  components: {
    FileHubUploader
  },
  data() {
    return {
      uploadToken: 'your-upload-token-here'
    }
  },
  methods: {
    handleUploadSuccess(files) {
      console.log('Upload successful:', files)
      // Handle successful uploads
    },
    handleUploadError(errors) {
      console.error('Upload errors:', errors)
      // Handle upload errors
    },
    handleFilesChanged(files) {
      console.log('Files changed:', files)
      // Handle file list changes
    }
  }
}
</script>
```

### React Component

```jsx
import React, { useState } from 'react';
import { FileHubUploader } from '@litepie/filehub-components/react';

function App() {
  const [uploadToken] = useState('your-upload-token-here');

  const handleUploadSuccess = (files) => {
    console.log('Upload successful:', files);
    // Handle successful uploads
  };

  const handleUploadError = (errors) => {
    console.error('Upload errors:', errors);
    // Handle upload errors
  };

  const handleFilesChanged = (files) => {
    console.log('Files changed:', files);
    // Handle file list changes
  };

  return (
    <div>
      <FileHubUploader
        uploadToken={uploadToken}
        collection="gallery"
        multiple={true}
        showCaption={true}
        uploadText="Drop your images here"
        onUploadSuccess={handleUploadSuccess}
        onUploadError={handleUploadError}
        onFilesChanged={handleFilesChanged}
      />
    </div>
  );
}

export default App;
```

## Component Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `uploadUrl` | String | `/api/filehub/upload` | API endpoint for file uploads |
| `uploadToken` | String | Required | Security token from FileHub backend |
| `collection` | String | `'default'` | Collection name for organizing files |
| `multiple` | Boolean | `true` | Allow multiple file selection |
| `accept` | String | `'image/*,video/*,audio/*,.pdf,.doc,.docx'` | Accepted file types |
| `maxSize` | Number | `10240` | Maximum file size in KB |
| `maxFiles` | Number | `10` | Maximum number of files |
| `showCaption` | Boolean | `true` | Show caption input field |
| `uploadText` | String | `'Drop files here or click to browse'` | Main upload prompt text |
| `uploadSubText` | String | `'Support for multiple files, drag to reorder'` | Secondary upload text |
| `titlePlaceholder` | String | `'Enter file title...'` | Placeholder for title input |
| `captionPlaceholder` | String | `'Enter file description...'` | Placeholder for caption input |
| `disabled` | Boolean | `false` | Disable the component |

## Events (Vue) / Callbacks (React)

### `upload-success` / `onUploadSuccess`
Fired when files are successfully uploaded.

```js
// Vue
@upload-success="handleSuccess"

// React
onUploadSuccess={(files) => console.log(files)}
```

**Parameters:**
- `files`: Array of successfully uploaded file objects

### `upload-error` / `onUploadError`
Fired when upload errors occur.

```js
// Vue
@upload-error="handleError"

// React
onUploadError={(errors) => console.error(errors)}
```

**Parameters:**
- `errors`: Array of error objects or error message string

### `files-changed` / `onFilesChanged`
Fired when the file list changes (add, remove, reorder).

```js
// Vue
@files-changed="handleFilesChanged"

// React
onFilesChanged={(files) => setFiles(files)}
```

**Parameters:**
- `files`: Current array of file objects

### `upload-progress` / `onUploadProgress`
Fired during file upload progress.

```js
// Vue
@upload-progress="handleProgress"

// React
onUploadProgress={(data) => console.log(data)}
```

**Parameters:**
- `data`: Object with `file` and `progress` properties

## File Object Structure

Each file in the component has the following structure:

```js
{
  id: 1,                    // Unique ID
  file: File,               // Native File object
  name: 'image.jpg',        // Original filename
  size: 1024000,            // File size in bytes
  type: 'image/jpeg',       // MIME type
  title: 'My Image',        // User-editable title
  caption: 'Description',   // User-editable caption
  preview: 'data:image...',  // Base64 preview (for images)
  uploading: false,         // Upload status
  progress: 0,              // Upload progress (0-100)
  error: null,              // Error message if any
  uploadedData: null        // Server response after upload
}
```

## Backend Integration

### Laravel Controller Setup

First, ensure you have an upload endpoint that works with the component:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Litepie\FileHub\Facades\FileHub;
use Litepie\FileHub\Facades\UploadToken;

class FileUploadController extends Controller
{
    public function upload(Request $request)
    {
        // Validate the upload token
        $tokenData = UploadToken::validateToken($request->upload_token);
        
        if (!$tokenData) {
            return response()->json(['error' => 'Invalid upload token'], 401);
        }
        
        $request->validate([
            'file' => 'required|file',
            'collection' => 'required|string',
            'title' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1000'
        ]);
        
        try {
            // Get the model instance (e.g., from route, session, etc.)
            $model = auth()->user(); // or however you determine the model
            
            $attachment = FileHub::attach(
                $model,
                $request->file('file'),
                $request->collection
            );
            
            // Update title and caption if provided
            if ($request->filled('title')) {
                $attachment->update(['metadata' => array_merge(
                    $attachment->metadata ?? [],
                    ['title' => $request->title]
                )]);
            }
            
            if ($request->filled('caption')) {
                $attachment->update(['metadata' => array_merge(
                    $attachment->metadata ?? [],
                    ['caption' => $request->caption]
                )]);
            }
            
            return response()->json([
                'success' => true,
                'file' => [
                    'id' => $attachment->id,
                    'url' => $attachment->url,
                    'thumbnail' => $attachment->getVariantUrl('thumbnail'),
                    'title' => $request->title,
                    'caption' => $request->caption,
                    'filename' => $attachment->filename,
                    'size' => $attachment->size,
                    'mime_type' => $attachment->mime_type
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function generateToken(Request $request)
    {
        $token = UploadToken::generateToken([
            'collection' => $request->get('collection', 'default'),
            'max_files' => 10,
            'allowed_mimes' => ['image/jpeg', 'image/png', 'image/gif'],
            'max_size' => 5120, // KB
            'expires_in' => 3600 // seconds
        ]);
        
        return response()->json(['token' => $token]);
    }
}
```

### Routes

Add routes to your `routes/web.php` or `routes/api.php`:

```php
use App\Http\Controllers\FileUploadController;

Route::middleware('auth')->group(function () {
    Route::post('/api/filehub/upload', [FileUploadController::class, 'upload']);
    Route::post('/api/filehub/token', [FileUploadController::class, 'generateToken']);
});
```

### Frontend Token Generation

Before using the component, generate an upload token from your backend:

```js
// Vue.js example
async created() {
  try {
    const response = await axios.post('/api/filehub/token', {
      collection: 'gallery'
    });
    this.uploadToken = response.data.token;
  } catch (error) {
    console.error('Failed to generate upload token:', error);
  }
}

// React example
useEffect(() => {
  const generateToken = async () => {
    try {
      const response = await axios.post('/api/filehub/token', {
        collection: 'gallery'
      });
      setUploadToken(response.data.token);
    } catch (error) {
      console.error('Failed to generate upload token:', error);
    }
  };
  
  generateToken();
}, []);
```

## Advanced Usage

### Custom Styling

The components use CSS classes that you can override:

```css
/* Custom upload zone styling */
.filehub-uploader .upload-zone {
  border: 3px dashed #your-color;
  background: #your-background;
}

/* Custom file item styling */
.filehub-uploader .file-item {
  border-radius: 16px;
  box-shadow: your-shadow;
}

/* Custom button styling */
.filehub-uploader .btn-primary {
  background: linear-gradient(45deg, #your-color1, #your-color2);
}
```

### Validation and Error Handling

```js
// Vue.js
methods: {
  handleUploadError(errors) {
    if (typeof errors === 'string') {
      // Single error message
      this.$toast.error(errors);
    } else {
      // Array of error objects
      errors.forEach(errorObj => {
        this.$toast.error(`${errorObj.file.name}: ${errorObj.error.message}`);
      });
    }
  }
}

// React
const handleUploadError = (errors) => {
  if (typeof errors === 'string') {
    toast.error(errors);
  } else {
    errors.forEach(errorObj => {
      toast.error(`${errorObj.file.name}: ${errorObj.error.message}`);
    });
  }
};
```

### File Type Restrictions

```html
<!-- Vue: Only images -->
<FileHubUploader
  accept="image/*"
  :max-size="5120"
  upload-text="Drop images here"
/>

<!-- React: Documents only -->
<FileHubUploader
  accept=".pdf,.doc,.docx,.txt"
  maxSize={10240}
  uploadText="Drop documents here"
/>
```

### Programmatic Control

```js
// Vue.js - Access component methods
this.$refs.uploader.clearAll();
this.$refs.uploader.startUpload();

// React - Use ref for control
const uploaderRef = useRef();

const clearFiles = () => {
  uploaderRef.current.clearAll();
};
```

## Browser Support

- Chrome 60+
- Firefox 55+
- Safari 12+
- Edge 79+

## Dependencies

### Vue.js Version
- Vue 3.x
- vuedraggable 4.x
- axios 1.x

### React Version
- React 18.x
- axios 1.x

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

MIT License. See [LICENSE](LICENSE) for details.
