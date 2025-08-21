/**
 * Vue.js Integration Example
 */

// main.js
import { createApp } from 'vue'
import { FileHubUploader } from '@litepie/filehub-components'
import axios from 'axios'

const app = createApp({
  components: {
    FileHubUploader
  },
  data() {
    return {
      uploadToken: null,
      uploadedFiles: []
    }
  },
  async mounted() {
    // Generate upload token when component mounts
    await this.generateUploadToken()
  },
  methods: {
    async generateUploadToken() {
      try {
        const response = await axios.post('/api/filehub/token', {
          collection: 'gallery',
          max_files: 10,
          max_size: 5120,
          allowed_mimes: ['image/jpeg', 'image/png', 'image/gif']
        })
        this.uploadToken = response.data.token
      } catch (error) {
        console.error('Failed to generate upload token:', error)
      }
    },
    
    handleUploadSuccess(files) {
      console.log('Upload successful:', files)
      this.uploadedFiles.push(...files)
      
      // Show success notification
      this.$toast.success(`${files.length} file(s) uploaded successfully!`)
      
      // Optionally refresh the token for next upload
      this.generateUploadToken()
    },
    
    handleUploadError(errors) {
      console.error('Upload errors:', errors)
      
      if (typeof errors === 'string') {
        this.$toast.error(errors)
      } else {
        errors.forEach(errorObj => {
          this.$toast.error(`${errorObj.file.name}: ${errorObj.error.message}`)
        })
      }
    },
    
    handleFilesChanged(files) {
      console.log('Files changed:', files)
      // Handle file list changes if needed
    }
  }
})

app.mount('#app')

// Component template
/*
<template>
  <div class="upload-section">
    <h2>Upload Gallery Images</h2>
    
    <FileHubUploader
      v-if="uploadToken"
      :upload-token="uploadToken"
      collection="gallery"
      :multiple="true"
      accept="image/*"
      :max-size="5120"
      :max-files="10"
      :show-caption="true"
      upload-text="Drop your images here"
      upload-sub-text="Supports JPG, PNG, GIF up to 5MB each"
      title-placeholder="Image title..."
      caption-placeholder="Describe this image..."
      @upload-success="handleUploadSuccess"
      @upload-error="handleUploadError"
      @files-changed="handleFilesChanged"
    />
    
    <div v-else class="loading">
      Generating upload token...
    </div>
    
    <!-- Display uploaded files -->
    <div v-if="uploadedFiles.length" class="uploaded-files">
      <h3>Uploaded Files</h3>
      <div class="file-grid">
        <div 
          v-for="fileData in uploadedFiles" 
          :key="fileData.response.id"
          class="file-card"
        >
          <img :src="fileData.response.thumbnail_url" :alt="fileData.file.title" />
          <h4>{{ fileData.file.title }}</h4>
          <p>{{ fileData.file.caption }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
*/
