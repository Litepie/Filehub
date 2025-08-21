import React, { useState, useCallback, useRef } from 'react';
import {
  View,
  Text,
  TouchableOpacity,
  ScrollView,
  Image,
  TextInput,
  Alert,
  Platform,
  PermissionsAndroid,
  Dimensions,
  ActivityIndicator,
  Modal,
} from 'react-native';
import DocumentPicker from 'react-native-document-picker';
import ImagePicker from 'react-native-image-crop-picker';
import DraggableFlatList from 'react-native-draggable-flatlist';
import { GestureHandlerRootView } from 'react-native-gesture-handler';
import RNFS from 'react-native-fs';
import Video from 'react-native-video';
import Icon from 'react-native-vector-icons/MaterialIcons';

const { width: screenWidth } = Dimensions.get('window');

const FileHubUploader = ({
  uploadUrl = '/api/filehub/upload',
  uploadToken,
  collection = 'default',
  multiple = true,
  maxSize = 10240, // KB
  maxFiles = 10,
  acceptedTypes = ['image', 'video', 'document'],
  showCaption = true,
  onUploadSuccess,
  onUploadError,
  onFilesChanged,
  onUploadProgress,
  style,
  theme = 'light',
}) => {
  const [files, setFiles] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState({});
  const [showPickerModal, setShowPickerModal] = useState(false);
  const uploadRefs = useRef({});

  const colors = {
    light: {
      background: '#ffffff',
      surface: '#f8f9fa',
      border: '#e9ecef',
      text: '#212529',
      textSecondary: '#6c757d',
      primary: '#007bff',
      success: '#28a745',
      error: '#dc3545',
      warning: '#ffc107',
    },
    dark: {
      background: '#1a1a1a',
      surface: '#2d2d2d',
      border: '#404040',
      text: '#ffffff',
      textSecondary: '#b0b0b0',
      primary: '#0d6efd',
      success: '#198754',
      error: '#dc3545',
      warning: '#ffc107',
    },
  };

  const currentColors = colors[theme];

  const styles = {
    container: {
      flex: 1,
      backgroundColor: currentColors.background,
      padding: 16,
      ...style,
    },
    uploadZone: {
      borderWidth: 2,
      borderStyle: 'dashed',
      borderColor: currentColors.border,
      borderRadius: 12,
      padding: 24,
      alignItems: 'center',
      justifyContent: 'center',
      backgroundColor: currentColors.surface,
      marginBottom: 16,
      minHeight: 120,
    },
    uploadZoneActive: {
      borderColor: currentColors.primary,
      backgroundColor: currentColors.primary + '10',
    },
    uploadIcon: {
      fontSize: 48,
      color: currentColors.textSecondary,
      marginBottom: 12,
    },
    uploadText: {
      fontSize: 16,
      fontWeight: '600',
      color: currentColors.text,
      marginBottom: 4,
      textAlign: 'center',
    },
    uploadSubText: {
      fontSize: 14,
      color: currentColors.textSecondary,
      textAlign: 'center',
    },
    fileList: {
      flex: 1,
    },
    fileItem: {
      flexDirection: 'row',
      backgroundColor: currentColors.surface,
      borderRadius: 8,
      padding: 12,
      marginBottom: 8,
      borderWidth: 1,
      borderColor: currentColors.border,
    },
    fileItemUploading: {
      borderColor: currentColors.primary,
      backgroundColor: currentColors.primary + '10',
    },
    fileItemError: {
      borderColor: currentColors.error,
      backgroundColor: currentColors.error + '10',
    },
    dragHandle: {
      width: 24,
      justifyContent: 'center',
      alignItems: 'center',
      marginRight: 12,
    },
    filePreview: {
      width: 60,
      height: 60,
      borderRadius: 8,
      backgroundColor: currentColors.border,
      marginRight: 12,
      overflow: 'hidden',
    },
    fileImage: {
      width: '100%',
      height: '100%',
    },
    fileIcon: {
      width: '100%',
      height: '100%',
      justifyContent: 'center',
      alignItems: 'center',
    },
    fileInfo: {
      flex: 1,
    },
    fileTitle: {
      borderWidth: 1,
      borderColor: currentColors.border,
      borderRadius: 4,
      padding: 8,
      fontSize: 14,
      fontWeight: '600',
      color: currentColors.text,
      backgroundColor: currentColors.background,
      marginBottom: 8,
    },
    fileCaption: {
      borderWidth: 1,
      borderColor: currentColors.border,
      borderRadius: 4,
      padding: 8,
      fontSize: 14,
      color: currentColors.text,
      backgroundColor: currentColors.background,
      height: 60,
      textAlignVertical: 'top',
      marginBottom: 8,
    },
    fileMeta: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      marginBottom: 8,
    },
    fileMetaText: {
      fontSize: 12,
      color: currentColors.textSecondary,
    },
    progressBar: {
      height: 4,
      backgroundColor: currentColors.border,
      borderRadius: 2,
      overflow: 'hidden',
      marginBottom: 8,
    },
    progressFill: {
      height: '100%',
      backgroundColor: currentColors.primary,
    },
    errorText: {
      fontSize: 12,
      color: currentColors.error,
      marginBottom: 8,
    },
    removeButton: {
      width: 32,
      height: 32,
      borderRadius: 16,
      backgroundColor: currentColors.error,
      justifyContent: 'center',
      alignItems: 'center',
      marginLeft: 8,
    },
    actionButtons: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      marginTop: 16,
      paddingTop: 16,
      borderTopWidth: 1,
      borderTopColor: currentColors.border,
    },
    button: {
      paddingHorizontal: 24,
      paddingVertical: 12,
      borderRadius: 8,
      alignItems: 'center',
      justifyContent: 'center',
      minWidth: 100,
    },
    buttonPrimary: {
      backgroundColor: currentColors.primary,
    },
    buttonSecondary: {
      backgroundColor: currentColors.textSecondary,
    },
    buttonText: {
      fontSize: 16,
      fontWeight: '600',
      color: '#ffffff',
    },
    modalOverlay: {
      flex: 1,
      backgroundColor: 'rgba(0, 0, 0, 0.5)',
      justifyContent: 'center',
      alignItems: 'center',
    },
    modalContent: {
      backgroundColor: currentColors.background,
      borderRadius: 12,
      padding: 24,
      width: screenWidth - 48,
      maxWidth: 400,
    },
    modalTitle: {
      fontSize: 18,
      fontWeight: '600',
      color: currentColors.text,
      marginBottom: 16,
      textAlign: 'center',
    },
    pickerOption: {
      flexDirection: 'row',
      alignItems: 'center',
      padding: 16,
      borderRadius: 8,
      marginBottom: 8,
      backgroundColor: currentColors.surface,
    },
    pickerIcon: {
      fontSize: 24,
      color: currentColors.primary,
      marginRight: 16,
    },
    pickerText: {
      fontSize: 16,
      color: currentColors.text,
      flex: 1,
    },
  };

  const requestPermissions = async () => {
    if (Platform.OS === 'android') {
      try {
        const grants = await PermissionsAndroid.requestMultiple([
          PermissionsAndroid.PERMISSIONS.CAMERA,
          PermissionsAndroid.PERMISSIONS.WRITE_EXTERNAL_STORAGE,
          PermissionsAndroid.PERMISSIONS.READ_EXTERNAL_STORAGE,
        ]);

        return (
          grants['android.permission.CAMERA'] === PermissionsAndroid.RESULTS.GRANTED &&
          grants['android.permission.WRITE_EXTERNAL_STORAGE'] === PermissionsAndroid.RESULTS.GRANTED &&
          grants['android.permission.READ_EXTERNAL_STORAGE'] === PermissionsAndroid.RESULTS.GRANTED
        );
      } catch (err) {
        console.warn(err);
        return false;
      }
    }
    return true;
  };

  const generateId = () => Math.random().toString(36).substr(2, 9);

  const formatFileSize = (bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  };

  const getFileIcon = (type) => {
    switch (type) {
      case 'image': return 'image';
      case 'video': return 'videocam';
      case 'document': return 'description';
      default: return 'insert-drive-file';
    }
  };

  const getFileType = (mimeType) => {
    if (mimeType.startsWith('image/')) return 'image';
    if (mimeType.startsWith('video/')) return 'video';
    if (mimeType.startsWith('audio/')) return 'audio';
    return 'document';
  };

  const validateFile = (file) => {
    const fileSizeKB = file.size / 1024;
    
    if (fileSizeKB > maxSize) {
      return `File size exceeds ${formatFileSize(maxSize * 1024)} limit`;
    }

    const fileType = getFileType(file.type);
    if (!acceptedTypes.includes(fileType)) {
      return `File type ${fileType} is not allowed`;
    }

    return null;
  };

  const addFiles = useCallback((newFiles) => {
    const validFiles = [];
    const errors = [];

    newFiles.forEach((file) => {
      if (files.length + validFiles.length >= maxFiles) {
        errors.push(`Maximum ${maxFiles} files allowed`);
        return;
      }

      const error = validateFile(file);
      if (error) {
        errors.push(`${file.name}: ${error}`);
        return;
      }

      // Check for duplicates
      const isDuplicate = files.some(
        (f) => f.name === file.name && f.size === file.size
      );
      if (isDuplicate) {
        return;
      }

      const fileData = {
        id: generateId(),
        ...file,
        title: file.name.split('.').slice(0, -1).join('.'),
        caption: '',
        uploading: false,
        progress: 0,
        error: null,
        uploadedData: null,
      };

      validFiles.push(fileData);
    });

    if (errors.length > 0) {
      onUploadError?.(errors.join('\n'));
    }

    if (validFiles.length > 0) {
      const updatedFiles = [...files, ...validFiles];
      setFiles(updatedFiles);
      onFilesChanged?.(updatedFiles);
    }
  }, [files, maxFiles, maxSize, acceptedTypes, onUploadError, onFilesChanged]);

  const pickFromCamera = async () => {
    setShowPickerModal(false);
    
    const hasPermission = await requestPermissions();
    if (!hasPermission) {
      Alert.alert('Permission Denied', 'Camera permission is required to take photos');
      return;
    }

    try {
      const result = await ImagePicker.openCamera({
        width: 1920,
        height: 1920,
        cropping: false,
        multiple: false,
        mediaType: 'any',
        includeBase64: false,
      });

      addFiles([{
        uri: result.path,
        name: `camera_${Date.now()}.${result.mime.split('/')[1]}`,
        type: result.mime,
        size: result.size,
      }]);
    } catch (error) {
      if (error.code !== 'E_PICKER_CANCELLED') {
        onUploadError?.('Failed to capture from camera');
      }
    }
  };

  const pickFromGallery = async () => {
    setShowPickerModal(false);
    
    try {
      const results = await ImagePicker.openPicker({
        multiple: multiple,
        mediaType: 'any',
        includeBase64: false,
        maxFiles: maxFiles,
      });

      const selectedFiles = Array.isArray(results) ? results : [results];
      const fileData = selectedFiles.map((result) => ({
        uri: result.path,
        name: result.filename || `file_${Date.now()}.${result.mime.split('/')[1]}`,
        type: result.mime,
        size: result.size,
      }));

      addFiles(fileData);
    } catch (error) {
      if (error.code !== 'E_PICKER_CANCELLED') {
        onUploadError?.('Failed to pick from gallery');
      }
    }
  };

  const pickFromFiles = async () => {
    setShowPickerModal(false);
    
    try {
      const results = await DocumentPicker.pick({
        type: [DocumentPicker.types.allFiles],
        allowMultiSelection: multiple,
      });

      const fileData = results.map((result) => ({
        uri: result.uri,
        name: result.name,
        type: result.type,
        size: result.size,
      }));

      addFiles(fileData);
    } catch (error) {
      if (!DocumentPicker.isCancel(error)) {
        onUploadError?.('Failed to pick files');
      }
    }
  };

  const updateFile = useCallback((id, field, value) => {
    const updatedFiles = files.map((file) =>
      file.id === id ? { ...file, [field]: value } : file
    );
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, onFilesChanged]);

  const removeFile = useCallback((id) => {
    const updatedFiles = files.filter((file) => file.id !== id);
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, onFilesChanged]);

  const clearAll = useCallback(() => {
    setFiles([]);
    onFilesChanged?.([]);
  }, [onFilesChanged]);

  const uploadFile = async (file) => {
    const formData = new FormData();
    formData.append('file', {
      uri: file.uri,
      type: file.type,
      name: file.name,
    });
    formData.append('collection', collection);
    formData.append('title', file.title || '');
    formData.append('caption', file.caption || '');
    formData.append('upload_token', uploadToken);

    const xhr = new XMLHttpRequest();
    
    return new Promise((resolve, reject) => {
      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) {
          const progress = Math.round((event.loaded * 100) / event.total);
          setUploadProgress(prev => ({ ...prev, [file.id]: progress }));
          onUploadProgress?.({ file, progress });
        }
      };

      xhr.onload = () => {
        if (xhr.status === 200) {
          try {
            const response = JSON.parse(xhr.responseText);
            resolve(response);
          } catch (error) {
            reject(new Error('Invalid response format'));
          }
        } else {
          reject(new Error(`Upload failed with status ${xhr.status}`));
        }
      };

      xhr.onerror = () => reject(new Error('Network error'));

      xhr.open('POST', uploadUrl);
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.send(formData);
      
      uploadRefs.current[file.id] = xhr;
    });
  };

  const startUpload = async () => {
    if (uploading || files.length === 0) return;

    setUploading(true);
    const uploadedFiles = [];
    const errors = [];

    for (const file of files) {
      try {
        setFiles(prev => prev.map(f => 
          f.id === file.id ? { ...f, uploading: true, error: null } : f
        ));

        const response = await uploadFile(file);
        
        setFiles(prev => prev.map(f => 
          f.id === file.id ? { ...f, uploading: false, uploadedData: response } : f
        ));

        uploadedFiles.push({ file, response });
      } catch (error) {
        setFiles(prev => prev.map(f => 
          f.id === file.id ? { 
            ...f, 
            uploading: false, 
            error: error.message || 'Upload failed' 
          } : f
        ));

        errors.push({ file, error });
      }
    }

    setUploading(false);

    if (uploadedFiles.length > 0) {
      onUploadSuccess?.(uploadedFiles);
    }

    if (errors.length > 0) {
      onUploadError?.(errors);
    }

    // Remove successfully uploaded files
    if (uploadedFiles.length > 0) {
      const remainingFiles = files.filter(f => !f.uploadedData);
      setFiles(remainingFiles);
      onFilesChanged?.(remainingFiles);
    }
  };

  const renderFile = ({ item: file, drag, isActive }) => (
    <View style={[
      styles.fileItem,
      file.uploading && styles.fileItemUploading,
      file.error && styles.fileItemError,
      isActive && { opacity: 0.7 }
    ]}>
      <TouchableOpacity 
        style={styles.dragHandle}
        onLongPress={drag}
        disabled={uploading}
      >
        <Icon name="drag-handle" size={20} color={currentColors.textSecondary} />
      </TouchableOpacity>

      <View style={styles.filePreview}>
        {getFileType(file.type) === 'image' ? (
          <Image source={{ uri: file.uri }} style={styles.fileImage} resizeMode="cover" />
        ) : getFileType(file.type) === 'video' ? (
          <Video 
            source={{ uri: file.uri }}
            style={styles.fileImage}
            paused={true}
            resizeMode="cover"
          />
        ) : (
          <View style={styles.fileIcon}>
            <Icon 
              name={getFileIcon(getFileType(file.type))} 
              size={24} 
              color={currentColors.textSecondary} 
            />
          </View>
        )}
      </View>

      <View style={styles.fileInfo}>
        <TextInput
          style={styles.fileTitle}
          value={file.title}
          onChangeText={(text) => updateFile(file.id, 'title', text)}
          placeholder="Enter file title..."
          placeholderTextColor={currentColors.textSecondary}
          editable={!uploading}
        />

        {showCaption && (
          <TextInput
            style={styles.fileCaption}
            value={file.caption}
            onChangeText={(text) => updateFile(file.id, 'caption', text)}
            placeholder="Enter file description..."
            placeholderTextColor={currentColors.textSecondary}
            multiline
            editable={!uploading}
          />
        )}

        <View style={styles.fileMeta}>
          <Text style={styles.fileMetaText}>{file.name}</Text>
          <Text style={styles.fileMetaText}>{formatFileSize(file.size)}</Text>
        </View>

        {file.uploading && (
          <View style={styles.progressBar}>
            <View 
              style={[
                styles.progressFill, 
                { width: `${uploadProgress[file.id] || 0}%` }
              ]} 
            />
          </View>
        )}

        {file.error && (
          <Text style={styles.errorText}>{file.error}</Text>
        )}
      </View>

      {(!uploading || file.error) && (
        <TouchableOpacity
          style={styles.removeButton}
          onPress={() => removeFile(file.id)}
        >
          <Icon name="close" size={16} color="#ffffff" />
        </TouchableOpacity>
      )}
    </View>
  );

  return (
    <GestureHandlerRootView style={styles.container}>
      <TouchableOpacity
        style={styles.uploadZone}
        onPress={() => setShowPickerModal(true)}
        disabled={uploading}
      >
        {uploading ? (
          <ActivityIndicator size="large" color={currentColors.primary} />
        ) : (
          <>
            <Icon name="cloud-upload" style={styles.uploadIcon} />
            <Text style={styles.uploadText}>Tap to select files</Text>
            <Text style={styles.uploadSubText}>
              Support for multiple files, drag to reorder
            </Text>
          </>
        )}
      </TouchableOpacity>

      {files.length > 0 && (
        <View style={styles.fileList}>
          <Text style={[styles.uploadText, { marginBottom: 16 }]}>
            {files.length} file{files.length !== 1 ? 's' : ''} selected
          </Text>

          <DraggableFlatList
            data={files}
            renderItem={renderFile}
            keyExtractor={(item) => item.id}
            onDragEnd={({ data }) => {
              setFiles(data);
              onFilesChanged?.(data);
            }}
            activationDistance={10}
          />
        </View>
      )}

      {files.length > 0 && (
        <View style={styles.actionButtons}>
          <TouchableOpacity
            style={[styles.button, styles.buttonSecondary]}
            onPress={clearAll}
            disabled={uploading}
          >
            <Text style={styles.buttonText}>Clear All</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[styles.button, styles.buttonPrimary]}
            onPress={startUpload}
            disabled={uploading || files.length === 0}
          >
            <Text style={styles.buttonText}>
              {uploading ? 'Uploading...' : `Upload ${files.length} file${files.length !== 1 ? 's' : ''}`}
            </Text>
          </TouchableOpacity>
        </View>
      )}

      <Modal
        visible={showPickerModal}
        transparent
        animationType="fade"
        onRequestClose={() => setShowPickerModal(false)}
      >
        <View style={styles.modalOverlay}>
          <View style={styles.modalContent}>
            <Text style={styles.modalTitle}>Select Source</Text>

            <TouchableOpacity style={styles.pickerOption} onPress={pickFromCamera}>
              <Icon name="camera-alt" style={styles.pickerIcon} />
              <Text style={styles.pickerText}>Camera</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.pickerOption} onPress={pickFromGallery}>
              <Icon name="photo-library" style={styles.pickerIcon} />
              <Text style={styles.pickerText}>Photo Gallery</Text>
            </TouchableOpacity>

            <TouchableOpacity style={styles.pickerOption} onPress={pickFromFiles}>
              <Icon name="folder" style={styles.pickerIcon} />
              <Text style={styles.pickerText}>Files</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, styles.buttonSecondary, { marginTop: 16 }]}
              onPress={() => setShowPickerModal(false)}
            >
              <Text style={styles.buttonText}>Cancel</Text>
            </TouchableOpacity>
          </View>
        </View>
      </Modal>
    </GestureHandlerRootView>
  );
};

export default FileHubUploader;
