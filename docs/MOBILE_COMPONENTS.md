# FileHub Mobile Components

Beautiful, feature-rich mobile components for the FileHub Laravel package. Available for both React Native and Flutter with native platform features.

## Features

- 📱 **Native Mobile Experience**: Platform-specific UI patterns and interactions
- 📸 **Camera Integration**: Take photos/videos directly from the app
- 🖼️ **Gallery Access**: Pick multiple files from device gallery
- 📂 **File System Access**: Browse and select any file type
- 🔄 **Drag & Drop Reordering**: Intuitive file reordering with native gestures
- ✏️ **Inline Editing**: Edit file titles and captions with native keyboards
- 📊 **Upload Progress**: Real-time progress indicators with native animations
- 🖼️ **Image Previews**: Native image rendering with error handling
- 🎨 **Theming**: Light/dark theme support with platform colors
- 📱 **Responsive**: Adapts to different screen sizes and orientations
- 🔒 **Permissions**: Handles camera, storage, and file access permissions
- ⚡ **Performance**: Optimized for mobile with efficient memory usage

## React Native Component

### Installation

```bash
npm install @litepie/filehub-react-native
```

### Dependencies

```bash
npm install react-native-document-picker react-native-image-crop-picker react-native-draggable-flatlist react-native-gesture-handler react-native-fs react-native-video react-native-vector-icons
```

### Platform Setup

#### iOS
Add to `ios/Podfile`:
```ruby
permissions_path = '../node_modules/react-native-permissions/ios'
pod 'Permission-Camera', :path => "#{permissions_path}/Camera"
pod 'Permission-PhotoLibrary', :path => "#{permissions_path}/PhotoLibrary"
```

Add to `ios/Info.plist`:
```xml
<key>NSCameraUsageDescription</key>
<string>This app needs access to camera to take photos</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>This app needs access to photo library to select images</string>
```

#### Android
Add to `android/app/src/main/AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
```

### Usage

```tsx
import React, { useState, useEffect } from 'react';
import { FileHubUploader } from '@litepie/filehub-react-native';
import axios from 'axios';

const App = () => {
  const [uploadToken, setUploadToken] = useState(null);

  useEffect(() => {
    generateToken();
  }, []);

  const generateToken = async () => {
    try {
      const response = await axios.post('/api/filehub/token', {
        collection: 'mobile-gallery'
      });
      setUploadToken(response.data.token);
    } catch (error) {
      console.error('Failed to generate token:', error);
    }
  };

  const handleUploadSuccess = (files) => {
    console.log('Upload successful:', files);
    // Handle successful uploads
  };

  const handleUploadError = (errors) => {
    console.error('Upload failed:', errors);
    // Handle upload errors
  };

  return (
    <FileHubUploader
      uploadToken={uploadToken}
      collection="mobile-gallery"
      multiple={true}
      maxSize={5120} // 5MB
      maxFiles={10}
      acceptedTypes={['image', 'video']}
      showCaption={true}
      theme="light"
      onUploadSuccess={handleUploadSuccess}
      onUploadError={handleUploadError}
      onFilesChanged={(files) => console.log('Files changed:', files)}
      onUploadProgress={(data) => console.log('Progress:', data)}
    />
  );
};

export default App;
```

### Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `uploadToken` | string | Required | Upload token from backend |
| `uploadUrl` | string | `/api/filehub/upload` | API endpoint |
| `collection` | string | `'default'` | File collection name |
| `multiple` | boolean | `true` | Allow multiple files |
| `maxSize` | number | `10240` | Max file size in KB |
| `maxFiles` | number | `10` | Max number of files |
| `acceptedTypes` | array | `['image', 'video', 'document']` | Accepted file types |
| `showCaption` | boolean | `true` | Show caption input |
| `theme` | string | `'light'` | Theme (`'light'` or `'dark'`) |
| `style` | object | `{}` | Custom styling |

### Events

```tsx
// Upload success - files successfully uploaded
onUploadSuccess={(files) => {
  files.forEach(({ file, response }) => {
    console.log(`${file.name} uploaded:`, response);
  });
}}

// Upload error - handle errors
onUploadError={(errors) => {
  if (typeof errors === 'string') {
    console.error('General error:', errors);
  } else {
    errors.forEach(({ file, error }) => {
      console.error(`${file.name} failed:`, error);
    });
  }
}}

// Files changed - list updated
onFilesChanged={(files) => {
  console.log('Current files:', files.length);
}}

// Upload progress - real-time progress
onUploadProgress={({ file, progress }) => {
  console.log(`${file.name}: ${progress}%`);
}}
```

## Flutter Component

### Installation

Add to `pubspec.yaml`:
```yaml
dependencies:
  filehub_uploader: ^1.0.0
```

### Platform Setup

#### iOS
Add to `ios/Runner/Info.plist`:
```xml
<key>NSCameraUsageDescription</key>
<string>This app needs access to camera to take photos</string>
<key>NSPhotoLibraryUsageDescription</key>
<string>This app needs access to photo library to select images</string>
```

#### Android
Add to `android/app/src/main/AndroidManifest.xml`:
```xml
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.READ_EXTERNAL_STORAGE" />
<uses-permission android:name="android.permission.WRITE_EXTERNAL_STORAGE" />
```

### Usage

```dart
import 'package:flutter/material.dart';
import 'package:filehub_uploader/filehub_uploader.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class MyApp extends StatefulWidget {
  @override
  _MyAppState createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  String? uploadToken;

  @override
  void initState() {
    super.initState();
    generateToken();
  }

  Future<void> generateToken() async {
    try {
      final response = await http.post(
        Uri.parse('/api/filehub/token'),
        headers: {'Content-Type': 'application/json'},
        body: json.encode({'collection': 'mobile-gallery'}),
      );
      
      if (response.statusCode == 200) {
        final data = json.decode(response.body);
        setState(() {
          uploadToken = data['token'];
        });
      }
    } catch (e) {
      print('Failed to generate token: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('FileHub Mobile Upload')),
      body: Padding(
        padding: EdgeInsets.all(16),
        child: uploadToken != null
            ? FileHubUploader(
                uploadToken: uploadToken!,
                collection: 'mobile-gallery',
                multiple: true,
                maxSize: 5120, // 5MB
                maxFiles: 10,
                acceptedTypes: ['image', 'video'],
                showCaption: true,
                onUploadSuccess: (files) {
                  print('Upload successful: $files');
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('${files.length} files uploaded!')),
                  );
                },
                onUploadError: (errors) {
                  print('Upload failed: $errors');
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Upload failed'),
                      backgroundColor: Colors.red,
                    ),
                  );
                },
                onFilesChanged: (files) {
                  print('Files changed: ${files.length}');
                },
                onUploadProgress: (file, progress) {
                  print('${file.name}: $progress%');
                },
              )
            : Center(child: CircularProgressIndicator()),
      ),
    );
  }
}
```

### Widget Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `uploadToken` | String | Required | Upload token from backend |
| `uploadUrl` | String | `/api/filehub/upload` | API endpoint |
| `collection` | String | `'default'` | File collection name |
| `multiple` | bool | `true` | Allow multiple files |
| `maxSize` | int | `10240` | Max file size in KB |
| `maxFiles` | int | `10` | Max number of files |
| `acceptedTypes` | List<String> | `['image', 'video', 'document']` | Accepted file types |
| `showCaption` | bool | `true` | Show caption input |
| `theme` | ThemeData? | `null` | Custom theme |

### Callbacks

```dart
// Upload success
onUploadSuccess: (List<Map<String, dynamic>> files) {
  for (var fileData in files) {
    print('${fileData['file'].name} uploaded: ${fileData['response']}');
  }
},

// Upload error
onUploadError: (dynamic errors) {
  if (errors is String) {
    print('General error: $errors');
  } else if (errors is List) {
    for (var error in errors) {
      print('${error['file'].name} failed: ${error['error']}');
    }
  }
},

// Files changed
onFilesChanged: (List<FileHubFile> files) {
  print('Current files: ${files.length}');
},

// Upload progress
onUploadProgress: (FileHubFile file, double progress) {
  print('${file.name}: ${progress.toStringAsFixed(1)}%');
},
```

## Advanced Features

### Custom Styling

#### React Native
```tsx
<FileHubUploader
  style={{
    backgroundColor: '#f8f9fa',
    borderRadius: 12,
    padding: 20,
  }}
  theme="dark"
  // ... other props
/>
```

#### Flutter
```dart
FileHubUploader(
  theme: ThemeData(
    primarySwatch: Colors.blue,
    brightness: Brightness.dark,
  ),
  // ... other props
)
```

### File Type Restrictions

```tsx
// React Native - Images only
acceptedTypes={['image']}

// React Native - Documents only  
acceptedTypes={['document']}

// React Native - Images and videos
acceptedTypes={['image', 'video']}
```

```dart
// Flutter - Images only
acceptedTypes: ['image'],

// Flutter - Documents only
acceptedTypes: ['document'],

// Flutter - Images and videos
acceptedTypes: ['image', 'video'],
```

### Error Handling

#### React Native
```tsx
const handleUploadError = (errors) => {
  if (typeof errors === 'string') {
    // General error
    Alert.alert('Error', errors);
  } else {
    // File-specific errors
    errors.forEach(({ file, error }) => {
      Alert.alert('Upload Failed', `${file.name}: ${error.message}`);
    });
  }
};
```

#### Flutter
```dart
onUploadError: (errors) {
  if (errors is String) {
    // General error
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(errors), backgroundColor: Colors.red),
    );
  } else if (errors is List) {
    // File-specific errors
    for (var error in errors) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${error['file'].name}: ${error['error']}'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }
},
```

### Progress Tracking

#### React Native
```tsx
const [uploadProgress, setUploadProgress] = useState({});

const handleUploadProgress = ({ file, progress }) => {
  setUploadProgress(prev => ({
    ...prev,
    [file.id]: progress
  }));
};

// Display overall progress
const overallProgress = Object.values(uploadProgress).reduce((a, b) => a + b, 0) / Object.keys(uploadProgress).length;
```

#### Flutter
```dart
Map<String, double> uploadProgress = {};

onUploadProgress: (file, progress) {
  setState(() {
    uploadProgress[file.id] = progress;
  });
},

// Display overall progress
double overallProgress = uploadProgress.isNotEmpty 
    ? uploadProgress.values.reduce((a, b) => a + b) / uploadProgress.length 
    : 0.0;
```

## Performance Optimization

### Memory Management
- Images are automatically resized on mobile devices
- Previews use efficient thumbnail generation
- Large files are processed in chunks

### Network Optimization
- Concurrent uploads with progress tracking
- Automatic retry on network errors
- Efficient multipart form data handling

### Battery Optimization
- Background upload prevention
- Efficient permission handling
- Minimal CPU usage during idle states

## Platform Differences

### iOS Specific
- Uses native `UIImagePickerController` for camera
- Integrates with Photos framework
- Supports Live Photos (converted to static images)
- Native permission dialogs

### Android Specific
- Uses `Intent.ACTION_PICK` for gallery selection
- Supports scoped storage (Android 10+)
- Native material design animations
- Runtime permission handling

## Troubleshooting

### Common Issues

#### Permission Denied
- Ensure all permissions are declared in manifests
- Handle runtime permissions properly
- Test on physical devices (simulators may behave differently)

#### Upload Failures
- Check network connectivity
- Verify upload token validity
- Ensure file size limits are respected
- Check server-side validation

#### Performance Issues
- Limit concurrent uploads
- Optimize image sizes before upload
- Use progress callbacks efficiently
- Avoid blocking the main thread

### Debug Mode

Enable debug logging:

#### React Native
```tsx
<FileHubUploader
  onUploadProgress={({ file, progress }) => {
    console.log(`Debug: ${file.name} - ${progress}%`);
  }}
  onFilesChanged={(files) => {
    console.log(`Debug: Files count - ${files.length}`);
  }}
  // ... other props
/>
```

#### Flutter
```dart
FileHubUploader(
  onUploadProgress: (file, progress) {
    print('Debug: ${file.name} - ${progress.toStringAsFixed(1)}%');
  },
  onFilesChanged: (files) {
    print('Debug: Files count - ${files.length}');
  },
  // ... other props
)
```

## License

MIT License. See [LICENSE](LICENSE) for details.
