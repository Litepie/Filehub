/**
 * React Integration Example
 */

import React, { useState, useEffect } from 'react';
import { FileHubUploader } from '@litepie/filehub-components/react';
import axios from 'axios';
import { toast } from 'react-hot-toast';

const GalleryUpload = () => {
  const [uploadToken, setUploadToken] = useState(null);
  const [uploadedFiles, setUploadedFiles] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    generateUploadToken();
  }, []);

  const generateUploadToken = async () => {
    try {
      setLoading(true);
      const response = await axios.post('/api/filehub/token', {
        collection: 'gallery',
        max_files: 10,
        max_size: 5120,
        allowed_mimes: ['image/jpeg', 'image/png', 'image/gif']
      });
      
      setUploadToken(response.data.token);
    } catch (error) {
      console.error('Failed to generate upload token:', error);
      toast.error('Failed to initialize uploader');
    } finally {
      setLoading(false);
    }
  };

  const handleUploadSuccess = (files) => {
    console.log('Upload successful:', files);
    setUploadedFiles(prev => [...prev, ...files]);
    
    toast.success(`${files.length} file(s) uploaded successfully!`);
    
    // Optionally refresh the token for next upload
    generateUploadToken();
  };

  const handleUploadError = (errors) => {
    console.error('Upload errors:', errors);
    
    if (typeof errors === 'string') {
      toast.error(errors);
    } else {
      errors.forEach(errorObj => {
        toast.error(`${errorObj.file.name}: ${errorObj.error.message}`);
      });
    }
  };

  const handleFilesChanged = (files) => {
    console.log('Files changed:', files);
    // Handle file list changes if needed
  };

  const handleUploadProgress = (data) => {
    console.log(`Upload progress: ${data.file.name} - ${data.progress}%`);
  };

  if (loading) {
    return <div className="loading">Generating upload token...</div>;
  }

  return (
    <div className="upload-section">
      <h2>Upload Gallery Images</h2>
      
      {uploadToken ? (
        <FileHubUploader
          uploadToken={uploadToken}
          collection="gallery"
          multiple={true}
          accept="image/*"
          maxSize={5120}
          maxFiles={10}
          showCaption={true}
          uploadText="Drop your images here"
          uploadSubText="Supports JPG, PNG, GIF up to 5MB each"
          titlePlaceholder="Image title..."
          captionPlaceholder="Describe this image..."
          onUploadSuccess={handleUploadSuccess}
          onUploadError={handleUploadError}
          onFilesChanged={handleFilesChanged}
          onUploadProgress={handleUploadProgress}
        />
      ) : (
        <div className="error">Failed to initialize uploader</div>
      )}
      
      {/* Display uploaded files */}
      {uploadedFiles.length > 0 && (
        <div className="uploaded-files">
          <h3>Uploaded Files</h3>
          <div className="file-grid">
            {uploadedFiles.map((fileData) => (
              <div key={fileData.response.id} className="file-card">
                <img 
                  src={fileData.response.thumbnail_url} 
                  alt={fileData.file.title}
                  className="file-thumbnail"
                />
                <h4>{fileData.file.title}</h4>
                <p>{fileData.file.caption}</p>
                <div className="file-meta">
                  <span>{fileData.response.human_size}</span>
                  <span>{fileData.response.file_type}</span>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};

// Example with custom hooks
const useFileUpload = (collection = 'default') => {
  const [uploadToken, setUploadToken] = useState(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState(null);

  const generateToken = async (config = {}) => {
    try {
      setError(null);
      const response = await axios.post('/api/filehub/token', {
        collection,
        ...config
      });
      setUploadToken(response.data.token);
      return response.data.token;
    } catch (err) {
      setError(err.message);
      return null;
    }
  };

  const uploadFiles = async (files) => {
    if (!uploadToken) {
      throw new Error('No upload token available');
    }

    setUploading(true);
    setError(null);

    try {
      const uploadPromises = files.map(async (fileData) => {
        const formData = new FormData();
        formData.append('file', fileData.file);
        formData.append('collection', collection);
        formData.append('title', fileData.title || '');
        formData.append('caption', fileData.caption || '');
        formData.append('upload_token', uploadToken);

        return axios.post('/api/filehub/upload', formData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
      });

      const results = await Promise.all(uploadPromises);
      return results.map(response => response.data);
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setUploading(false);
    }
  };

  return {
    uploadToken,
    uploading,
    error,
    generateToken,
    uploadFiles
  };
};

// Usage with custom hook
const CustomUploadComponent = () => {
  const { uploadToken, uploading, error, generateToken } = useFileUpload('gallery');
  const [files, setFiles] = useState([]);

  useEffect(() => {
    generateToken({
      max_files: 5,
      max_size: 2048,
      allowed_mimes: ['image/jpeg', 'image/png']
    });
  }, []);

  return (
    <div>
      {uploadToken && (
        <FileHubUploader
          uploadToken={uploadToken}
          collection="gallery"
          onFilesChanged={setFiles}
          disabled={uploading}
        />
      )}
      {error && <div className="error">{error}</div>}
    </div>
  );
};

export default GalleryUpload;
export { useFileUpload, CustomUploadComponent };
