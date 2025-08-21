@props([
    'name' => 'files',
    'collection' => 'default',
    'multiple' => false,
    'accept' => null,
    'maxSize' => null,
    'preview' => true,
])

<div x-data="fileUpload" class="filehub-upload">
    <div class="upload-zone" 
         @drop.prevent="handleDrop"
         @dragover.prevent
         @dragenter.prevent
         :class="{ 'dragover': isDragOver }"
         @dragenter="isDragOver = true"
         @dragleave="isDragOver = false">
        
        <input 
            type="file"
            name="{{ $name }}"
            {{ $multiple ? 'multiple' : '' }}
            {{ $accept ? 'accept=' . $accept : '' }}
            @change="handleFileSelect"
            class="hidden"
            x-ref="fileInput">
        
        <div class="upload-content">
            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                      d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" />
            </svg>
            
            <p class="upload-text">
                Drop files here or 
                <button type="button" @click="$refs.fileInput.click()" class="upload-button">
                    browse
                </button>
            </p>
            
            @if($maxSize)
            <p class="upload-hint">Maximum file size: {{ $maxSize }}KB</p>
            @endif
        </div>
    </div>

    @if($preview)
    <div x-show="files.length > 0" class="file-preview">
        <template x-for="(file, index) in files" :key="index">
            <div class="file-item">
                <div class="file-info">
                    <span x-text="file.name" class="file-name"></span>
                    <span x-text="formatFileSize(file.size)" class="file-size"></span>
                </div>
                <button type="button" @click="removeFile(index)" class="remove-file">
                    ×
                </button>
            </div>
        </template>
    </div>
    @endif
</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('fileUpload', () => ({
        files: [],
        isDragOver: false,

        handleDrop(e) {
            this.isDragOver = false;
            const files = Array.from(e.dataTransfer.files);
            this.addFiles(files);
        },

        handleFileSelect(e) {
            const files = Array.from(e.target.files);
            this.addFiles(files);
        },

        addFiles(newFiles) {
            {{ $multiple ? 'this.files.push(...newFiles);' : 'this.files = newFiles.slice(0, 1);' }}
        },

        removeFile(index) {
            this.files.splice(index, 1);
        },

        formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return `${size.toFixed(1)} ${units[unitIndex]}`;
        }
    }));
});
</script>

<style>
.filehub-upload .upload-zone {
    border: 2px dashed #d1d5db;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    transition: all 0.2s;
    cursor: pointer;
}

.filehub-upload .upload-zone.dragover {
    border-color: #3b82f6;
    background-color: #eff6ff;
}

.filehub-upload .upload-icon {
    width: 3rem;
    height: 3rem;
    margin: 0 auto 1rem;
    color: #9ca3af;
}

.filehub-upload .upload-button {
    color: #3b82f6;
    text-decoration: underline;
    background: none;
    border: none;
    cursor: pointer;
}

.filehub-upload .file-preview {
    margin-top: 1rem;
}

.filehub-upload .file-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.25rem;
    margin-bottom: 0.5rem;
}

.filehub-upload .file-name {
    font-weight: 500;
}

.filehub-upload .file-size {
    color: #6b7280;
    font-size: 0.875rem;
}

.filehub-upload .remove-file {
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    width: 1.5rem;
    height: 1.5rem;
    cursor: pointer;
}
</style>
