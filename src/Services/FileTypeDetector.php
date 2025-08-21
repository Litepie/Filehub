<?php

namespace Litepie\FileHub\Services;

use Illuminate\Http\UploadedFile;

class FileTypeDetector
{
    public function isImage(UploadedFile $file): bool
    {
        return $this->isImageMimeType($file->getMimeType());
    }

    public function isImageMimeType(string $mimeType): bool
    {
        return str_starts_with($mimeType, 'image/');
    }

    public function isVideo(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'video/');
    }

    public function isAudio(UploadedFile $file): bool
    {
        return str_starts_with($file->getMimeType(), 'audio/');
    }

    public function isDocument(UploadedFile $file): bool
    {
        $documentMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'text/csv',
            'application/json',
        ];

        return in_array($file->getMimeType(), $documentMimes);
    }

    public function isArchive(UploadedFile $file): bool
    {
        $archiveMimes = [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
        ];

        return in_array($file->getMimeType(), $archiveMimes);
    }

    public function getFileCategory(UploadedFile $file): string
    {
        return match (true) {
            $this->isImage($file) => 'image',
            $this->isVideo($file) => 'video',
            $this->isAudio($file) => 'audio',
            $this->isDocument($file) => 'document',
            $this->isArchive($file) => 'archive',
            default => 'other'
        };
    }
}
