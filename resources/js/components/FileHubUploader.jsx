import React, { useState, useRef, useCallback } from 'react';
import axios from 'axios';
import './FileHubUploader.css';

const FileHubUploader = ({
  uploadUrl = '/api/filehub/upload',
  uploadToken,
  collection = 'default',
  multiple = true,
  accept = 'image/*,video/*,audio/*,.pdf,.doc,.docx',
  maxSize = 10240, // KB
  maxFiles = 10,
  showCaption = true,
  uploadText = 'Drop files here or click to browse',
  uploadSubText = 'Support for multiple files, drag to reorder',
  titlePlaceholder = 'Enter file title...',
  captionPlaceholder = 'Enter file description...',
  disabled = false,
  onUploadSuccess,
  onUploadError,
  onFilesChanged,
  onUploadProgress
}) => {
  const [files, setFiles] = useState([]);
  const [isDragOver, setIsDragOver] = useState(false);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadingCount, setUploadingCount] = useState(0);
  const [totalFiles, setTotalFiles] = useState(0);
  const [nextId, setNextId] = useState(1);
  const fileInputRef = useRef(null);

  const triggerFileInput = useCallback(() => {
    if (!isUploading && !disabled && fileInputRef.current) {
      fileInputRef.current.click();
    }
  }, [isUploading, disabled]);

  const handleFileSelect = useCallback((event) => {
    const selectedFiles = Array.from(event.target.files);
    addFiles(selectedFiles);
    event.target.value = ''; // Reset input
  }, []);

  const handleDrop = useCallback((event) => {
    event.preventDefault();
    setIsDragOver(false);
    if (isUploading || disabled) return;
    
    const droppedFiles = Array.from(event.dataTransfer.files);
    addFiles(droppedFiles);
  }, [isUploading, disabled]);

  const addFiles = useCallback((newFiles) => {
    const validFiles = [];
    
    for (const file of newFiles) {
      // Check file count limit
      if (files.length + validFiles.length >= maxFiles) {
        onUploadError?.(`Maximum ${maxFiles} files allowed`);
        break;
      }
      
      // Check file size
      if (file.size > maxSize * 1024) {
        onUploadError?.(`File "${file.name}" is too large. Maximum size is ${formatFileSize(maxSize * 1024)}`);
        continue;
      }
      
      // Check if file already exists
      if (files.some(f => f.name === file.name && f.size === file.size)) {
        continue;
      }
      
      const fileData = {
        id: nextId,
        file: file,
        name: file.name,
        size: file.size,
        type: file.type,
        title: getDefaultTitle(file.name),
        caption: '',
        preview: null,
        uploading: false,
        progress: 0,
        error: null,
        uploadedData: null
      };
      
      setNextId(prev => prev + 1);
      
      // Generate preview for images
      if (file.type.startsWith('image/')) {
        generatePreview(fileData);
      }
      
      validFiles.push(fileData);
    }
    
    const updatedFiles = [...files, ...validFiles];
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, maxFiles, maxSize, nextId, onUploadError, onFilesChanged]);

  const generatePreview = useCallback((fileData) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      setFiles(prevFiles => 
        prevFiles.map(f => 
          f.id === fileData.id 
            ? { ...f, preview: e.target.result }
            : f
        )
      );
    };
    reader.readAsDataURL(fileData.file);
  }, []);

  const getDefaultTitle = useCallback((filename) => {
    return filename.split('.').slice(0, -1).join('.');
  }, []);

  const removeFile = useCallback((id) => {
    const updatedFiles = files.filter(f => f.id !== id);
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, onFilesChanged]);

  const clearAll = useCallback(() => {
    setFiles([]);
    onFilesChanged?.([]);
  }, [onFilesChanged]);

  const updateFile = useCallback((id, field, value) => {
    const updatedFiles = files.map(f => 
      f.id === id ? { ...f, [field]: value } : f
    );
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, onFilesChanged]);

  const moveFile = useCallback((dragIndex, dropIndex) => {
    const updatedFiles = [...files];
    const [draggedFile] = updatedFiles.splice(dragIndex, 1);
    updatedFiles.splice(dropIndex, 0, draggedFile);
    setFiles(updatedFiles);
    onFilesChanged?.(updatedFiles);
  }, [files, onFilesChanged]);

  const startUpload = useCallback(async () => {
    if (isUploading || files.length === 0) return;
    
    setIsUploading(true);
    setUploadingCount(0);
    setTotalFiles(files.length);
    
    const uploadedFiles = [];
    const errors = [];
    
    for (const fileData of files) {
      try {
        // Update file state to uploading
        setFiles(prevFiles => 
          prevFiles.map(f => 
            f.id === fileData.id 
              ? { ...f, uploading: true, progress: 0, error: null }
              : f
          )
        );
        
        const formData = new FormData();
        formData.append('file', fileData.file);
        formData.append('collection', collection);
        formData.append('title', fileData.title || '');
        formData.append('caption', fileData.caption || '');
        formData.append('upload_token', uploadToken);
        
        const response = await axios.post(uploadUrl, formData, {
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          onUploadProgress: (progressEvent) => {
            const progress = Math.round(
              (progressEvent.loaded * 100) / progressEvent.total
            );
            
            setFiles(prevFiles => 
              prevFiles.map(f => 
                f.id === fileData.id 
                  ? { ...f, progress }
                  : f
              )
            );
            
            onUploadProgress?.({
              file: fileData,
              progress
            });
          }
        });
        
        setFiles(prevFiles => 
          prevFiles.map(f => 
            f.id === fileData.id 
              ? { ...f, uploading: false, uploadedData: response.data }
              : f
          )
        );
        
        uploadedFiles.push({
          file: fileData,
          response: response.data
        });
        setUploadingCount(prev => prev + 1);
        
      } catch (error) {
        setFiles(prevFiles => 
          prevFiles.map(f => 
            f.id === fileData.id 
              ? { 
                  ...f, 
                  uploading: false, 
                  error: error.response?.data?.message || 'Upload failed' 
                }
              : f
          )
        );
        
        errors.push({
          file: fileData,
          error: error
        });
      }
    }
    
    setIsUploading(false);
    
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
  }, [isUploading, files, collection, uploadToken, uploadUrl, onUploadSuccess, onUploadError, onFilesChanged, onUploadProgress]);

  const formatFileSize = useCallback((bytes) => {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }, []);

  // Drag and drop handlers for file reordering
  const handleDragStart = useCallback((e, index) => {
    e.dataTransfer.setData('text/plain', index);
  }, []);

  const handleDragOver = useCallback((e) => {
    e.preventDefault();
  }, []);

  const handleDropReorder = useCallback((e, dropIndex) => {
    e.preventDefault();
    const dragIndex = parseInt(e.dataTransfer.getData('text/plain'));
    if (dragIndex !== dropIndex) {
      moveFile(dragIndex, dropIndex);
    }
  }, [moveFile]);

  return (
    <div className="filehub-uploader">
      {/* Upload Drop Zone */}
      <div
        className={`upload-zone ${isDragOver ? 'drag-over' : ''} ${isUploading || disabled ? 'upload-disabled' : ''}`}
        onDrop={handleDrop}
        onDragOver={(e) => {
          e.preventDefault();
          setIsDragOver(true);
        }}
        onDragLeave={() => setIsDragOver(false)}
        onClick={triggerFileInput}
      >
        <input
          ref={fileInputRef}
          type="file"
          multiple={multiple}
          accept={accept}
          disabled={isUploading || disabled}
          onChange={handleFileSelect}
          style={{ display: 'none' }}
        />
        
        <div className="upload-content">
          {!isUploading ? (
            <div className="upload-prompt">
              <svg className="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                <polyline points="7,10 12,15 17,10"/>
                <line x1="12" y1="15" x2="12" y2="3"/>
              </svg>
              <p className="upload-text">
                <span className="upload-main">{uploadText}</span>
                <span className="upload-sub">{uploadSubText}</span>
              </p>
            </div>
          ) : (
            <div className="upload-progress">
              <svg className="spinner" viewBox="0 0 24 24">
                <circle className="spinner-circle" cx="12" cy="12" r="10" fill="none" stroke="currentColor" strokeWidth="2"/>
              </svg>
              <p>Uploading {uploadingCount} of {totalFiles} files...</p>
            </div>
          )}
        </div>
      </div>

      {/* File List */}
      {files.length > 0 && (
        <div className="file-list">
          <h3 className="file-list-title">
            {files.length} file{files.length !== 1 ? 's' : ''} selected
          </h3>
          
          <div className="file-items">
            {files.map((file, index) => (
              <div
                key={file.id}
                className={`file-item ${file.uploading ? 'uploading' : ''} ${file.error ? 'error' : ''}`}
                draggable={!isUploading}
                onDragStart={(e) => handleDragStart(e, index)}
                onDragOver={handleDragOver}
                onDrop={(e) => handleDropReorder(e, index)}
              >
                {/* Drag Handle */}
                {!isUploading && (
                  <div className="drag-handle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <line x1="3" y1="6" x2="21" y2="6"/>
                      <line x1="3" y1="12" x2="21" y2="12"/>
                      <line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                  </div>
                )}

                {/* File Preview */}
                <div className="file-preview">
                  {file.preview ? (
                    <img
                      src={file.preview}
                      alt={file.name}
                      className="file-thumbnail"
                    />
                  ) : (
                    <div className="file-icon">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                      </svg>
                    </div>
                  )}
                </div>

                {/* File Info and Controls */}
                <div className="file-info">
                  <div className="file-details">
                    <input
                      value={file.title}
                      disabled={isUploading}
                      placeholder={titlePlaceholder}
                      className="file-title-input"
                      onChange={(e) => updateFile(file.id, 'title', e.target.value)}
                    />
                    
                    {showCaption && (
                      <textarea
                        value={file.caption}
                        disabled={isUploading}
                        placeholder={captionPlaceholder}
                        className="file-caption-input"
                        rows="2"
                        onChange={(e) => updateFile(file.id, 'caption', e.target.value)}
                      />
                    )}
                    
                    <div className="file-meta">
                      <span className="file-name">{file.name}</span>
                      <span className="file-size">{formatFileSize(file.size)}</span>
                    </div>
                  </div>

                  {/* Progress Bar */}
                  {file.uploading && (
                    <div className="upload-progress-bar">
                      <div 
                        className="progress-fill" 
                        style={{ width: `${file.progress}%` }}
                      />
                    </div>
                  )}

                  {/* Error Message */}
                  {file.error && (
                    <div className="error-message">
                      {file.error}
                    </div>
                  )}
                </div>

                {/* Remove Button */}
                {(!isUploading || file.error) && (
                  <button
                    onClick={() => removeFile(file.id)}
                    className="remove-button"
                    type="button"
                  >
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                      <line x1="18" y1="6" x2="6" y2="18"/>
                      <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Action Buttons */}
      {files.length > 0 && (
        <div className="action-buttons">
          <button
            onClick={clearAll}
            disabled={isUploading}
            className="btn btn-secondary"
            type="button"
          >
            Clear All
          </button>
          
          <button
            onClick={startUpload}
            disabled={isUploading || files.length === 0}
            className="btn btn-primary"
            type="button"
          >
            {!isUploading ? (
              <span>Upload {files.length} file{files.length !== 1 ? 's' : ''}</span>
            ) : (
              <span>Uploading...</span>
            )}
          </button>
        </div>
      )}
    </div>
  );
};

export default FileHubUploader;
