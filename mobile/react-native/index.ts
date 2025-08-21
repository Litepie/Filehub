export { default as FileHubUploader } from './FileHubUploader';

export interface FileHubFile {
  id: string;
  uri: string;
  name: string;
  type: string;
  size: number;
  title: string;
  caption: string;
  uploading: boolean;
  progress: number;
  error: string | null;
  uploadedData: any;
}

export interface FileHubUploaderProps {
  uploadUrl?: string;
  uploadToken: string;
  collection?: string;
  multiple?: boolean;
  maxSize?: number;
  maxFiles?: number;
  acceptedTypes?: ('image' | 'video' | 'audio' | 'document')[];
  showCaption?: boolean;
  onUploadSuccess?: (files: { file: FileHubFile; response: any }[]) => void;
  onUploadError?: (errors: string | { file: FileHubFile; error: any }[]) => void;
  onFilesChanged?: (files: FileHubFile[]) => void;
  onUploadProgress?: (data: { file: FileHubFile; progress: number }) => void;
  style?: any;
  theme?: 'light' | 'dark';
}
