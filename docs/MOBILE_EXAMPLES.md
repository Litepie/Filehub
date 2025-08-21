# FileHub Complete Example App

This example demonstrates a complete mobile application using FileHub components with both React Native and Flutter implementations.

## React Native Example

### Complete App Structure

```
filehub-example-app/
├── src/
│   ├── components/
│   │   ├── FileManager.tsx
│   │   ├── GalleryView.tsx
│   │   └── UploadProgress.tsx
│   ├── screens/
│   │   ├── HomeScreen.tsx
│   │   ├── UploadScreen.tsx
│   │   └── GalleryScreen.tsx
│   ├── services/
│   │   ├── api.ts
│   │   └── fileHub.ts
│   └── App.tsx
├── package.json
└── README.md
```

### Main App Component

```tsx
// src/App.tsx
import React, { useEffect, useState } from 'react';
import {
  NavigationContainer,
  DefaultTheme,
  DarkTheme,
} from '@react-navigation/native';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import {
  SafeAreaProvider,
  SafeAreaView,
} from 'react-native-safe-area-context';
import {
  StatusBar,
  useColorScheme,
} from 'react-native';
import Icon from 'react-native-vector-icons/MaterialIcons';

import HomeScreen from './screens/HomeScreen';
import UploadScreen from './screens/UploadScreen';
import GalleryScreen from './screens/GalleryScreen';
import { ApiService } from './services/api';

const Tab = createBottomTabNavigator();

const App = () => {
  const isDarkMode = useColorScheme() === 'dark';
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    // Initialize API and check authentication
    ApiService.initialize('https://your-app.com/api');
    checkAuthentication();
  }, []);

  const checkAuthentication = async () => {
    try {
      const token = await ApiService.getAuthToken();
      setIsAuthenticated(!!token);
    } catch (error) {
      console.error('Auth check failed:', error);
    }
  };

  return (
    <SafeAreaProvider>
      <NavigationContainer theme={isDarkMode ? DarkTheme : DefaultTheme}>
        <SafeAreaView style={{ flex: 1 }}>
          <StatusBar
            barStyle={isDarkMode ? 'light-content' : 'dark-content'}
            backgroundColor={isDarkMode ? '#000' : '#fff'}
          />
          <Tab.Navigator
            screenOptions={({ route }) => ({
              tabBarIcon: ({ focused, color, size }) => {
                let iconName;
                switch (route.name) {
                  case 'Home':
                    iconName = 'home';
                    break;
                  case 'Upload':
                    iconName = 'cloud-upload';
                    break;
                  case 'Gallery':
                    iconName = 'photo-library';
                    break;
                  default:
                    iconName = 'help';
                }
                return <Icon name={iconName} size={size} color={color} />;
              },
              tabBarActiveTintColor: '#007AFF',
              tabBarInactiveTintColor: 'gray',
            })}
          >
            <Tab.Screen name="Home" component={HomeScreen} />
            <Tab.Screen name="Upload" component={UploadScreen} />
            <Tab.Screen name="Gallery" component={GalleryScreen} />
          </Tab.Navigator>
        </SafeAreaView>
      </NavigationContainer>
    </SafeAreaProvider>
  );
};

export default App;
```

### Upload Screen

```tsx
// src/screens/UploadScreen.tsx
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  Alert,
  ScrollView,
  RefreshControl,
} from 'react-native';
import { FileHubUploader } from '@litepie/filehub-react-native';
import { FileHubService } from '../services/fileHub';
import UploadProgress from '../components/UploadProgress';

const UploadScreen = () => {
  const [uploadToken, setUploadToken] = useState<string | null>(null);
  const [uploads, setUploads] = useState<any[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    initializeUpload();
  }, []);

  const initializeUpload = async () => {
    try {
      setIsLoading(true);
      const token = await FileHubService.generateUploadToken('user-photos');
      setUploadToken(token);
    } catch (error) {
      Alert.alert('Error', 'Failed to initialize upload');
      console.error('Upload initialization failed:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleUploadSuccess = (files: any[]) => {
    Alert.alert(
      'Success',
      `${files.length} file(s) uploaded successfully!`,
      [
        {
          text: 'OK',
          onPress: () => {
            // Refresh gallery or navigate
            setUploads(prev => [...prev, ...files]);
          },
        },
      ]
    );
  };

  const handleUploadError = (errors: any) => {
    console.error('Upload errors:', errors);
    
    if (typeof errors === 'string') {
      Alert.alert('Upload Failed', errors);
    } else {
      const errorMessages = errors.map((error: any) => 
        `${error.file.name}: ${error.error.message || error.error}`
      ).join('\n');
      
      Alert.alert('Upload Failed', errorMessages);
    }
  };

  const handleUploadProgress = (data: any) => {
    setUploads(prev => 
      prev.map(upload => 
        upload.file.id === data.file.id 
          ? { ...upload, progress: data.progress }
          : upload
      )
    );
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await initializeUpload();
    setRefreshing(false);
  };

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <Text>Initializing upload...</Text>
      </View>
    );
  }

  return (
    <ScrollView
      style={styles.container}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <View style={styles.header}>
        <Text style={styles.title}>Upload Files</Text>
        <Text style={styles.subtitle}>
          Upload photos, videos, and documents to your gallery
        </Text>
      </View>

      {uploadToken && (
        <FileHubUploader
          uploadToken={uploadToken}
          collection="user-photos"
          multiple={true}
          maxSize={10240} // 10MB
          maxFiles={20}
          acceptedTypes={['image', 'video', 'document']}
          showCaption={true}
          theme="light"
          onUploadSuccess={handleUploadSuccess}
          onUploadError={handleUploadError}
          onUploadProgress={handleUploadProgress}
          onFilesChanged={(files) => {
            console.log('Files changed:', files.length);
          }}
          style={styles.uploader}
        />
      )}

      {uploads.length > 0 && (
        <View style={styles.progressSection}>
          <Text style={styles.progressTitle}>Recent Uploads</Text>
          {uploads.map((upload, index) => (
            <UploadProgress key={index} upload={upload} />
          ))}
        </View>
      )}
    </ScrollView>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    padding: 20,
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#e9ecef',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#212529',
    marginBottom: 5,
  },
  subtitle: {
    fontSize: 16,
    color: '#6c757d',
  },
  uploader: {
    margin: 20,
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
    shadowColor: '#000',
    shadowOffset: {
      width: 0,
      height: 2,
    },
    shadowOpacity: 0.1,
    shadowRadius: 3.84,
    elevation: 5,
  },
  progressSection: {
    margin: 20,
    backgroundColor: 'white',
    borderRadius: 12,
    padding: 20,
  },
  progressTitle: {
    fontSize: 18,
    fontWeight: '600',
    marginBottom: 15,
    color: '#212529',
  },
});

export default UploadScreen;
```

### Gallery Screen

```tsx
// src/screens/GalleryScreen.tsx
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
  Alert,
  Dimensions,
} from 'react-native';
import GalleryView from '../components/GalleryView';
import { FileHubService } from '../services/fileHub';

const { width } = Dimensions.get('window');
const numColumns = 3;
const itemSize = (width - 40 - (numColumns - 1) * 10) / numColumns;

const GalleryScreen = () => {
  const [files, setFiles] = useState<any[]>([]);
  const [refreshing, setRefreshing] = useState(false);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadGallery();
  }, []);

  const loadGallery = async () => {
    try {
      setLoading(true);
      const galleryFiles = await FileHubService.getFiles('user-photos');
      setFiles(galleryFiles);
    } catch (error) {
      Alert.alert('Error', 'Failed to load gallery');
      console.error('Gallery load failed:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDeleteFile = async (fileId: string) => {
    Alert.alert(
      'Delete File',
      'Are you sure you want to delete this file?',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Delete',
          style: 'destructive',
          onPress: async () => {
            try {
              await FileHubService.deleteFile(fileId);
              setFiles(prev => prev.filter(file => file.id !== fileId));
            } catch (error) {
              Alert.alert('Error', 'Failed to delete file');
            }
          },
        },
      ]
    );
  };

  const handleUpdateFile = async (fileId: string, updates: any) => {
    try {
      await FileHubService.updateFile(fileId, updates);
      setFiles(prev => 
        prev.map(file => 
          file.id === fileId ? { ...file, ...updates } : file
        )
      );
    } catch (error) {
      Alert.alert('Error', 'Failed to update file');
    }
  };

  const onRefresh = async () => {
    setRefreshing(true);
    await loadGallery();
    setRefreshing(false);
  };

  const renderFile = ({ item, index }: { item: any; index: number }) => (
    <GalleryView
      file={item}
      size={itemSize}
      onDelete={() => handleDeleteFile(item.id)}
      onUpdate={(updates) => handleUpdateFile(item.id, updates)}
    />
  );

  if (loading) {
    return (
      <View style={styles.loadingContainer}>
        <Text>Loading gallery...</Text>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>My Gallery</Text>
        <Text style={styles.subtitle}>
          {files.length} file(s) in your collection
        </Text>
      </View>

      <FlatList
        data={files}
        renderItem={renderFile}
        keyExtractor={(item) => item.id}
        numColumns={numColumns}
        contentContainerStyle={styles.gallery}
        columnWrapperStyle={styles.row}
        refreshControl={
          <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
        }
        ListEmptyComponent={
          <View style={styles.emptyContainer}>
            <Text style={styles.emptyText}>No files yet</Text>
            <Text style={styles.emptySubtext}>
              Go to Upload tab to add your first files
            </Text>
          </View>
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f8f9fa',
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  header: {
    padding: 20,
    backgroundColor: 'white',
    borderBottomWidth: 1,
    borderBottomColor: '#e9ecef',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    color: '#212529',
    marginBottom: 5,
  },
  subtitle: {
    fontSize: 16,
    color: '#6c757d',
  },
  gallery: {
    padding: 20,
  },
  row: {
    justifyContent: 'space-between',
  },
  emptyContainer: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 60,
  },
  emptyText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#6c757d',
    marginBottom: 5,
  },
  emptySubtext: {
    fontSize: 14,
    color: '#adb5bd',
    textAlign: 'center',
  },
});

export default GalleryScreen;
```

### API Service

```tsx
// src/services/api.ts
import axios, { AxiosInstance, AxiosRequestConfig } from 'axios';
import AsyncStorage from '@react-native-async-storage/async-storage';

class ApiService {
  private api: AxiosInstance;
  private baseURL: string = '';

  constructor() {
    this.api = axios.create({
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
    });

    this.setupInterceptors();
  }

  initialize(baseURL: string) {
    this.baseURL = baseURL;
    this.api.defaults.baseURL = baseURL;
  }

  private setupInterceptors() {
    // Request interceptor
    this.api.interceptors.request.use(
      async (config) => {
        const token = await this.getAuthToken();
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Response interceptor
    this.api.interceptors.response.use(
      (response) => response,
      async (error) => {
        if (error.response?.status === 401) {
          await this.clearAuthToken();
          // Handle unauthorized - redirect to login
        }
        return Promise.reject(error);
      }
    );
  }

  async getAuthToken(): Promise<string | null> {
    try {
      return await AsyncStorage.getItem('auth_token');
    } catch (error) {
      console.error('Failed to get auth token:', error);
      return null;
    }
  }

  async setAuthToken(token: string): Promise<void> {
    try {
      await AsyncStorage.setItem('auth_token', token);
    } catch (error) {
      console.error('Failed to set auth token:', error);
    }
  }

  async clearAuthToken(): Promise<void> {
    try {
      await AsyncStorage.removeItem('auth_token');
    } catch (error) {
      console.error('Failed to clear auth token:', error);
    }
  }

  async get<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.get(url, config);
    return response.data;
  }

  async post<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.post(url, data, config);
    return response.data;
  }

  async put<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.put(url, data, config);
    return response.data;
  }

  async patch<T = any>(url: string, data?: any, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.patch(url, data, config);
    return response.data;
  }

  async delete<T = any>(url: string, config?: AxiosRequestConfig): Promise<T> {
    const response = await this.api.delete(url, config);
    return response.data;
  }

  // Upload with progress
  async upload<T = any>(
    url: string,
    formData: FormData,
    onProgress?: (progress: number) => void
  ): Promise<T> {
    const response = await this.api.post(url, formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
      onUploadProgress: (progressEvent) => {
        if (onProgress && progressEvent.total) {
          const progress = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          );
          onProgress(progress);
        }
      },
    });
    return response.data;
  }
}

export default new ApiService();
```

### FileHub Service

```tsx
// src/services/fileHub.ts
import ApiService from './api';

export class FileHubService {
  static async generateUploadToken(collection: string = 'default'): Promise<string> {
    const response = await ApiService.post('/filehub/token', {
      collection,
      expires_in: 3600, // 1 hour
    });
    return response.token;
  }

  static async getFiles(collection: string = 'default'): Promise<any[]> {
    const response = await ApiService.get(`/filehub/files?collection=${collection}`);
    return response.data;
  }

  static async getFile(fileId: string): Promise<any> {
    return await ApiService.get(`/filehub/files/${fileId}`);
  }

  static async updateFile(fileId: string, updates: any): Promise<any> {
    return await ApiService.patch(`/filehub/files/${fileId}`, updates);
  }

  static async deleteFile(fileId: string): Promise<void> {
    await ApiService.delete(`/filehub/files/${fileId}`);
  }

  static async reorderFiles(fileIds: string[]): Promise<void> {
    await ApiService.post('/filehub/reorder', { file_ids: fileIds });
  }

  static async getUploadStats(): Promise<any> {
    return await ApiService.get('/filehub/stats');
  }

  static async searchFiles(query: string, collection?: string): Promise<any[]> {
    const params = new URLSearchParams({ q: query });
    if (collection) {
      params.append('collection', collection);
    }
    
    const response = await ApiService.get(`/filehub/search?${params.toString()}`);
    return response.data;
  }

  static async getFileUrl(fileId: string, variant: string = 'original'): Promise<string> {
    const response = await ApiService.get(`/filehub/files/${fileId}/url?variant=${variant}`);
    return response.url;
  }

  static async downloadFile(fileId: string): Promise<Blob> {
    const response = await ApiService.get(`/filehub/files/${fileId}/download`, {
      responseType: 'blob',
    });
    return response;
  }
}
```

## Flutter Example

### Complete App Structure

```
filehub_flutter_example/
├── lib/
│   ├── models/
│   │   ├── file_model.dart
│   │   └── upload_model.dart
│   ├── screens/
│   │   ├── home_screen.dart
│   │   ├── upload_screen.dart
│   │   └── gallery_screen.dart
│   ├── services/
│   │   ├── api_service.dart
│   │   └── filehub_service.dart
│   ├── widgets/
│   │   ├── file_grid.dart
│   │   ├── upload_progress.dart
│   │   └── gallery_item.dart
│   └── main.dart
├── pubspec.yaml
└── README.md
```

### Main App

```dart
// lib/main.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'screens/home_screen.dart';
import 'screens/upload_screen.dart';
import 'screens/gallery_screen.dart';
import 'services/api_service.dart';

void main() {
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<ApiService>(
          create: (_) => ApiService()..initialize('https://your-app.com/api'),
        ),
      ],
      child: MaterialApp(
        title: 'FileHub Example',
        theme: ThemeData(
          primarySwatch: Colors.blue,
          visualDensity: VisualDensity.adaptivePlatformDensity,
        ),
        home: MainScreen(),
      ),
    );
  }
}

class MainScreen extends StatefulWidget {
  @override
  _MainScreenState createState() => _MainScreenState();
}

class _MainScreenState extends State<MainScreen> {
  int _currentIndex = 0;

  final List<Widget> _screens = [
    HomeScreen(),
    UploadScreen(),
    GalleryScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        items: [
          BottomNavigationBarItem(
            icon: Icon(Icons.home),
            label: 'Home',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.cloud_upload),
            label: 'Upload',
          ),
          BottomNavigationBarItem(
            icon: Icon(Icons.photo_library),
            label: 'Gallery',
          ),
        ],
      ),
    );
  }
}
```

### Upload Screen

```dart
// lib/screens/upload_screen.dart
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:filehub_uploader/filehub_uploader.dart';
import '../services/filehub_service.dart';

class UploadScreen extends StatefulWidget {
  @override
  _UploadScreenState createState() => _UploadScreenState();
}

class _UploadScreenState extends State<UploadScreen> {
  String? uploadToken;
  bool isLoading = true;
  List<Map<String, dynamic>> recentUploads = [];

  @override
  void initState() {
    super.initState();
    initializeUpload();
  }

  Future<void> initializeUpload() async {
    try {
      setState(() => isLoading = true);
      final token = await FileHubService.generateUploadToken('user-photos');
      setState(() {
        uploadToken = token;
        isLoading = false;
      });
    } catch (e) {
      setState(() => isLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to initialize upload: $e'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void handleUploadSuccess(List<Map<String, dynamic>> files) {
    setState(() {
      recentUploads.addAll(files);
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${files.length} file(s) uploaded successfully!'),
        backgroundColor: Colors.green,
      ),
    );
  }

  void handleUploadError(dynamic errors) {
    String message = 'Upload failed';
    
    if (errors is String) {
      message = errors;
    } else if (errors is List) {
      message = errors.map((e) => '${e['file'].name}: ${e['error']}').join('\n');
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        duration: Duration(seconds: 4),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Upload Files'),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh),
            onPressed: initializeUpload,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: initializeUpload,
        child: SingleChildScrollView(
          physics: AlwaysScrollableScrollPhysics(),
          padding: EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Container(
                padding: EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Upload Your Files',
                      style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Upload photos, videos, and documents to your personal gallery',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.grey.shade700,
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 24),

              // Upload Component
              if (isLoading)
                Center(
                  child: Column(
                    children: [
                      CircularProgressIndicator(),
                      SizedBox(height: 16),
                      Text('Initializing upload...'),
                    ],
                  ),
                )
              else if (uploadToken != null)
                Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.grey.withOpacity(0.1),
                        spreadRadius: 1,
                        blurRadius: 5,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                  child: FileHubUploader(
                    uploadToken: uploadToken!,
                    collection: 'user-photos',
                    multiple: true,
                    maxSize: 10240, // 10MB
                    maxFiles: 20,
                    acceptedTypes: ['image', 'video', 'document'],
                    showCaption: true,
                    onUploadSuccess: handleUploadSuccess,
                    onUploadError: handleUploadError,
                    onFilesChanged: (files) {
                      print('Files in uploader: ${files.length}');
                    },
                    onUploadProgress: (file, progress) {
                      print('${file.name}: ${progress.toStringAsFixed(1)}%');
                    },
                  ),
                ),

              // Recent Uploads
              if (recentUploads.isNotEmpty) ...[
                SizedBox(height: 32),
                Text(
                  'Recent Uploads',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
                ),
                SizedBox(height: 16),
                Container(
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.grey.withOpacity(0.1),
                        spreadRadius: 1,
                        blurRadius: 5,
                        offset: Offset(0, 2),
                      ),
                    ],
                  ),
                  child: ListView.separated(
                    shrinkWrap: true,
                    physics: NeverScrollableScrollPhysics(),
                    itemCount: recentUploads.length,
                    separatorBuilder: (context, index) => Divider(height: 1),
                    itemBuilder: (context, index) {
                      final upload = recentUploads[index];
                      final file = upload['file'];
                      return ListTile(
                        leading: CircleAvatar(
                          backgroundColor: Colors.green,
                          child: Icon(Icons.check, color: Colors.white),
                        ),
                        title: Text(file.name),
                        subtitle: Text('Uploaded successfully'),
                        trailing: Text(
                          _formatFileSize(file.size),
                          style: TextStyle(color: Colors.grey),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  String _formatFileSize(int bytes) {
    if (bytes < 1024) return '$bytes B';
    if (bytes < 1024 * 1024) return '${(bytes / 1024).toStringAsFixed(1)} KB';
    return '${(bytes / (1024 * 1024)).toStringAsFixed(1)} MB';
  }
}
```

### API Service

```dart
// lib/services/api_service.dart
import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  late String _baseUrl;
  String? _authToken;
  late http.Client _client;

  ApiService() {
    _client = http.Client();
  }

  void initialize(String baseUrl) {
    _baseUrl = baseUrl;
    _loadAuthToken();
  }

  Future<void> _loadAuthToken() async {
    final prefs = await SharedPreferences.getInstance();
    _authToken = prefs.getString('auth_token');
  }

  Future<void> setAuthToken(String token) async {
    _authToken = token;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('auth_token', token);
  }

  Future<void> clearAuthToken() async {
    _authToken = null;
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('auth_token');
  }

  Map<String, String> _getHeaders({Map<String, String>? additionalHeaders}) {
    final headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    };

    if (_authToken != null) {
      headers['Authorization'] = 'Bearer $_authToken';
    }

    if (additionalHeaders != null) {
      headers.addAll(additionalHeaders);
    }

    return headers;
  }

  Future<dynamic> get(String endpoint) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final response = await _client.get(url, headers: _getHeaders());
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> post(String endpoint, {Map<String, dynamic>? data}) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final response = await _client.post(
        url,
        headers: _getHeaders(),
        body: data != null ? json.encode(data) : null,
      );
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> put(String endpoint, {Map<String, dynamic>? data}) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final response = await _client.put(
        url,
        headers: _getHeaders(),
        body: data != null ? json.encode(data) : null,
      );
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> patch(String endpoint, {Map<String, dynamic>? data}) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final response = await _client.patch(
        url,
        headers: _getHeaders(),
        body: data != null ? json.encode(data) : null,
      );
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> delete(String endpoint) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final response = await _client.delete(url, headers: _getHeaders());
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  Future<dynamic> uploadFile(
    String endpoint,
    File file, {
    Map<String, String>? fields,
    Function(int, int)? onProgress,
  }) async {
    final url = Uri.parse('$_baseUrl$endpoint');
    
    try {
      final request = http.MultipartRequest('POST', url);
      
      // Add headers
      final headers = _getHeaders();
      headers.remove('Content-Type'); // Let http handle multipart content type
      request.headers.addAll(headers);
      
      // Add fields
      if (fields != null) {
        request.fields.addAll(fields);
      }
      
      // Add file
      final multipartFile = await http.MultipartFile.fromPath(
        'file',
        file.path,
      );
      request.files.add(multipartFile);
      
      // Send request
      final streamedResponse = await _client.send(request);
      final response = await http.Response.fromStream(streamedResponse);
      
      return _handleResponse(response);
    } catch (e) {
      throw _handleError(e);
    }
  }

  dynamic _handleResponse(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return null;
      return json.decode(response.body);
    } else {
      throw HttpException(
        'HTTP ${response.statusCode}: ${response.reasonPhrase}',
      );
    }
  }

  Exception _handleError(dynamic error) {
    if (error is SocketException) {
      return Exception('No internet connection');
    } else if (error is HttpException) {
      return error;
    } else if (error is FormatException) {
      return Exception('Invalid response format');
    } else {
      return Exception('Unknown error: $error');
    }
  }

  void dispose() {
    _client.close();
  }
}
```

This complete example demonstrates:

1. **Full App Structure**: Both React Native and Flutter apps with proper navigation
2. **Authentication**: Token management and API integration
3. **File Management**: Upload, view, edit, and delete operations
4. **Error Handling**: Comprehensive error handling and user feedback
5. **Performance**: Optimized for mobile with proper state management
6. **Platform Integration**: Native file access, camera, and permissions
7. **Real-world Usage**: Production-ready code with best practices

The examples show how to integrate FileHub components into complete mobile applications with proper architecture, state management, and user experience considerations.
