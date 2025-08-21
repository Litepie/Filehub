<template>
  <div class="filehub-uploader">
    <!-- Upload Drop Zone -->
    <div
      class="upload-zone"
      :class="{
        'drag-over': isDragOver,
        'upload-disabled': isUploading || disabled
      }"
      @drop.prevent="handleDrop"
      @dragover.prevent="isDragOver = true"
      @dragleave.prevent="isDragOver = false"
      @click="triggerFileInput"
    >
      <input
        ref="fileInput"
        type="file"
        :multiple="multiple"
        :accept="accept"
        :disabled="isUploading || disabled"
        @change="handleFileSelect"
        style="display: none;"
      />
      
      <div class="upload-content">
        <div v-if="!isUploading" class="upload-prompt">
          <svg class="upload-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
            <polyline points="7,10 12,15 17,10"/>
            <line x1="12" y1="15" x2="12" y2="3"/>
          </svg>
          <p class="upload-text">
            <span class="upload-main">{{ uploadText }}</span>
            <span class="upload-sub">{{ uploadSubText }}</span>
          </p>
        </div>
        
        <div v-else class="upload-progress">
          <svg class="spinner" viewBox="0 0 24 24">
            <circle class="spinner-circle" cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/>
          </svg>
          <p>Uploading {{ uploadingCount }} of {{ totalFiles }} files...</p>
        </div>
      </div>
    </div>

    <!-- File List -->
    <div v-if="files.length > 0" class="file-list">
      <h3 class="file-list-title">
        {{ files.length }} file{{ files.length !== 1 ? 's' : '' }} selected
      </h3>
      
      <draggable
        v-model="files"
        :disabled="isUploading"
        item-key="id"
        @change="handleReorder"
        class="file-items"
      >
        <template #item="{ element: file }">
          <div class="file-item" :class="{ 'uploading': file.uploading, 'error': file.error }">
            <!-- Drag Handle -->
            <div class="drag-handle" v-if="!isUploading">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
              </svg>
            </div>

            <!-- File Preview -->
            <div class="file-preview">
              <img
                v-if="file.preview"
                :src="file.preview"
                :alt="file.name"
                class="file-thumbnail"
              />
              <div v-else class="file-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                  <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
                </svg>
              </div>
            </div>

            <!-- File Info and Controls -->
            <div class="file-info">
              <div class="file-details">
                <input
                  v-model="file.title"
                  :disabled="isUploading"
                  :placeholder="titlePlaceholder"
                  class="file-title-input"
                  @input="updateFile(file.id, 'title', $event.target.value)"
                />
                
                <textarea
                  v-if="showCaption"
                  v-model="file.caption"
                  :disabled="isUploading"
                  :placeholder="captionPlaceholder"
                  class="file-caption-input"
                  rows="2"
                  @input="updateFile(file.id, 'caption', $event.target.value)"
                ></textarea>
                
                <div class="file-meta">
                  <span class="file-name">{{ file.name }}</span>
                  <span class="file-size">{{ formatFileSize(file.size) }}</span>
                </div>
              </div>

              <!-- Progress Bar -->
              <div v-if="file.uploading" class="upload-progress-bar">
                <div 
                  class="progress-fill" 
                  :style="{ width: file.progress + '%' }"
                ></div>
              </div>

              <!-- Error Message -->
              <div v-if="file.error" class="error-message">
                {{ file.error }}
              </div>
            </div>

            <!-- Remove Button -->
            <button
              v-if="!isUploading || file.error"
              @click="removeFile(file.id)"
              class="remove-button"
              type="button"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <line x1="18" y1="6" x2="6" y2="18"/>
                <line x1="6" y1="6" x2="18" y2="18"/>
              </svg>
            </button>
          </div>
        </template>
      </draggable>
    </div>

    <!-- Action Buttons -->
    <div v-if="files.length > 0" class="action-buttons">
      <button
        @click="clearAll"
        :disabled="isUploading"
        class="btn btn-secondary"
        type="button"
      >
        Clear All
      </button>
      
      <button
        @click="startUpload"
        :disabled="isUploading || files.length === 0"
        class="btn btn-primary"
        type="button"
      >
        <span v-if="!isUploading">Upload {{ files.length }} file{{ files.length !== 1 ? 's' : '' }}</span>
        <span v-else>Uploading...</span>
      </button>
    </div>
  </div>
</template>

<script>
import draggable from 'vuedraggable'
import axios from 'axios'

export default {
  name: 'FileHubUploader',
  components: {
    draggable
  },
  props: {
    uploadUrl: {
      type: String,
      default: '/api/filehub/upload'
    },
    uploadToken: {
      type: String,
      required: true
    },
    collection: {
      type: String,
      default: 'default'
    },
    multiple: {
      type: Boolean,
      default: true
    },
    accept: {
      type: String,
      default: 'image/*,video/*,audio/*,.pdf,.doc,.docx'
    },
    maxSize: {
      type: Number,
      default: 10240 // KB
    },
    maxFiles: {
      type: Number,
      default: 10
    },
    showCaption: {
      type: Boolean,
      default: true
    },
    uploadText: {
      type: String,
      default: 'Drop files here or click to browse'
    },
    uploadSubText: {
      type: String,
      default: 'Support for multiple files, drag to reorder'
    },
    titlePlaceholder: {
      type: String,
      default: 'Enter file title...'
    },
    captionPlaceholder: {
      type: String,
      default: 'Enter file description...'
    },
    disabled: {
      type: Boolean,
      default: false
    }
  },
  emits: ['upload-success', 'upload-error', 'files-changed', 'upload-progress'],
  data() {
    return {
      files: [],
      isDragOver: false,
      isUploading: false,
      uploadingCount: 0,
      totalFiles: 0,
      nextId: 1
    }
  },
  methods: {
    triggerFileInput() {
      if (!this.isUploading && !this.disabled) {
        this.$refs.fileInput.click()
      }
    },
    
    handleFileSelect(event) {
      const files = Array.from(event.target.files)
      this.addFiles(files)
      event.target.value = '' // Reset input
    },
    
    handleDrop(event) {
      this.isDragOver = false
      if (this.isUploading || this.disabled) return
      
      const files = Array.from(event.dataTransfer.files)
      this.addFiles(files)
    },
    
    addFiles(newFiles) {
      const validFiles = []
      
      for (const file of newFiles) {
        // Check file count limit
        if (this.files.length + validFiles.length >= this.maxFiles) {
          this.$emit('upload-error', `Maximum ${this.maxFiles} files allowed`)
          break
        }
        
        // Check file size
        if (file.size > this.maxSize * 1024) {
          this.$emit('upload-error', `File "${file.name}" is too large. Maximum size is ${this.formatFileSize(this.maxSize * 1024)}`)
          continue
        }
        
        // Check if file already exists
        if (this.files.some(f => f.name === file.name && f.size === file.size)) {
          continue
        }
        
        const fileData = {
          id: this.nextId++,
          file: file,
          name: file.name,
          size: file.size,
          type: file.type,
          title: this.getDefaultTitle(file.name),
          caption: '',
          preview: null,
          uploading: false,
          progress: 0,
          error: null,
          uploadedData: null
        }
        
        // Generate preview for images
        if (file.type.startsWith('image/')) {
          this.generatePreview(fileData)
        }
        
        validFiles.push(fileData)
      }
      
      this.files.push(...validFiles)
      this.$emit('files-changed', this.files)
    },
    
    generatePreview(fileData) {
      const reader = new FileReader()
      reader.onload = (e) => {
        fileData.preview = e.target.result
      }
      reader.readAsDataURL(fileData.file)
    },
    
    getDefaultTitle(filename) {
      return filename.split('.').slice(0, -1).join('.')
    },
    
    removeFile(id) {
      this.files = this.files.filter(f => f.id !== id)
      this.$emit('files-changed', this.files)
    },
    
    clearAll() {
      this.files = []
      this.$emit('files-changed', this.files)
    },
    
    updateFile(id, field, value) {
      const file = this.files.find(f => f.id === id)
      if (file) {
        file[field] = value
        this.$emit('files-changed', this.files)
      }
    },
    
    handleReorder() {
      this.$emit('files-changed', this.files)
    },
    
    async startUpload() {
      if (this.isUploading || this.files.length === 0) return
      
      this.isUploading = true
      this.uploadingCount = 0
      this.totalFiles = this.files.length
      
      const uploadedFiles = []
      const errors = []
      
      for (const fileData of this.files) {
        try {
          fileData.uploading = true
          fileData.progress = 0
          fileData.error = null
          
          const formData = new FormData()
          formData.append('file', fileData.file)
          formData.append('collection', this.collection)
          formData.append('title', fileData.title || '')
          formData.append('caption', fileData.caption || '')
          formData.append('upload_token', this.uploadToken)
          
          const response = await axios.post(this.uploadUrl, formData, {
            headers: {
              'Content-Type': 'multipart/form-data'
            },
            onUploadProgress: (progressEvent) => {
              fileData.progress = Math.round(
                (progressEvent.loaded * 100) / progressEvent.total
              )
              this.$emit('upload-progress', {
                file: fileData,
                progress: fileData.progress
              })
            }
          })
          
          fileData.uploading = false
          fileData.uploadedData = response.data
          uploadedFiles.push({
            file: fileData,
            response: response.data
          })
          this.uploadingCount++
          
        } catch (error) {
          fileData.uploading = false
          fileData.error = error.response?.data?.message || 'Upload failed'
          errors.push({
            file: fileData,
            error: error
          })
        }
      }
      
      this.isUploading = false
      
      if (uploadedFiles.length > 0) {
        this.$emit('upload-success', uploadedFiles)
      }
      
      if (errors.length > 0) {
        this.$emit('upload-error', errors)
      }
      
      // Remove successfully uploaded files
      if (uploadedFiles.length > 0) {
        this.files = this.files.filter(f => !f.uploadedData)
        this.$emit('files-changed', this.files)
      }
    },
    
    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
    }
  }
}
</script>

<style scoped>
.filehub-uploader {
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.upload-zone {
  border: 2px dashed #d1d5db;
  border-radius: 12px;
  padding: 3rem 2rem;
  text-align: center;
  transition: all 0.3s ease;
  cursor: pointer;
  background: #fafafa;
}

.upload-zone:hover {
  border-color: #3b82f6;
  background: #f8faff;
}

.upload-zone.drag-over {
  border-color: #3b82f6;
  background: #eff6ff;
  transform: scale(1.02);
}

.upload-zone.upload-disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.upload-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1rem;
}

.upload-icon {
  width: 3rem;
  height: 3rem;
  color: #6b7280;
  margin-bottom: 1rem;
}

.upload-text {
  margin: 0;
}

.upload-main {
  display: block;
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 0.5rem;
}

.upload-sub {
  display: block;
  font-size: 0.875rem;
  color: #6b7280;
}

.spinner {
  width: 2rem;
  height: 2rem;
  animation: spin 1s linear infinite;
}

.spinner-circle {
  stroke-dasharray: 31.416;
  stroke-dashoffset: 31.416;
  animation: progress 2s ease-in-out infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

@keyframes progress {
  0% { stroke-dashoffset: 31.416; }
  50% { stroke-dashoffset: 15.708; }
  100% { stroke-dashoffset: 31.416; }
}

.file-list {
  margin-top: 2rem;
}

.file-list-title {
  font-size: 1.125rem;
  font-weight: 600;
  color: #374151;
  margin-bottom: 1rem;
}

.file-items {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.file-item {
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: white;
  transition: all 0.2s ease;
}

.file-item:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.file-item.uploading {
  background: #f8faff;
  border-color: #3b82f6;
}

.file-item.error {
  background: #fef2f2;
  border-color: #ef4444;
}

.drag-handle {
  cursor: grab;
  color: #9ca3af;
  padding: 0.25rem;
}

.drag-handle:active {
  cursor: grabbing;
}

.drag-handle svg {
  width: 1rem;
  height: 1rem;
}

.file-preview {
  flex-shrink: 0;
  width: 4rem;
  height: 4rem;
  border-radius: 6px;
  overflow: hidden;
  background: #f3f4f6;
  display: flex;
  align-items: center;
  justify-content: center;
}

.file-thumbnail {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.file-icon {
  color: #6b7280;
}

.file-icon svg {
  width: 2rem;
  height: 2rem;
}

.file-info {
  flex: 1;
  min-width: 0;
}

.file-details {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.file-title-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 0.875rem;
  font-weight: 600;
}

.file-title-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.file-caption-input {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  font-size: 0.875rem;
  resize: vertical;
  min-height: 2.5rem;
}

.file-caption-input:focus {
  outline: none;
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.file-meta {
  display: flex;
  gap: 1rem;
  font-size: 0.75rem;
  color: #6b7280;
}

.upload-progress-bar {
  width: 100%;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
  margin-top: 0.5rem;
}

.progress-fill {
  height: 100%;
  background: #3b82f6;
  transition: width 0.3s ease;
}

.error-message {
  color: #ef4444;
  font-size: 0.75rem;
  margin-top: 0.25rem;
}

.remove-button {
  flex-shrink: 0;
  width: 2rem;
  height: 2rem;
  border: none;
  background: #f3f4f6;
  border-radius: 4px;
  color: #6b7280;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s ease;
}

.remove-button:hover {
  background: #ef4444;
  color: white;
}

.remove-button svg {
  width: 1rem;
  height: 1rem;
}

.action-buttons {
  display: flex;
  gap: 1rem;
  justify-content: flex-end;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e5e7eb;
}

.btn {
  padding: 0.75rem 1.5rem;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.btn-secondary {
  background: #f3f4f6;
  color: #374151;
}

.btn-secondary:hover:not(:disabled) {
  background: #e5e7eb;
}

.btn-primary {
  background: #3b82f6;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2563eb;
}

/* Responsive Design */
@media (max-width: 640px) {
  .file-item {
    flex-direction: column;
    align-items: stretch;
  }
  
  .file-preview {
    align-self: flex-start;
  }
  
  .action-buttons {
    flex-direction: column;
  }
  
  .upload-zone {
    padding: 2rem 1rem;
  }
}
</style>
