<?php

namespace Litepie\FileHub\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Litepie\FileHub\Contracts\FileHubManagerContract;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Events\FileAttached;
use Litepie\FileHub\Events\FileDetached;
use Litepie\FileHub\Events\FileProcessed;
use Litepie\FileHub\Jobs\ProcessImageVariants;
use Litepie\FileHub\Jobs\CleanupFile;
use Litepie\FileHub\Services\SecurityValidator;
use Litepie\FileHub\Services\FileTypeDetector;
use Litepie\FileHub\Services\ImageProcessor;
use Litepie\FileHub\Exceptions\FileHubException;
use Litepie\FileHub\Exceptions\SecurityException;

class FileHubManager implements FileHubManagerContract
{
    public function __construct(
        private SecurityValidator $securityValidator,
        private FileTypeDetector $fileTypeDetector,
        private ImageProcessor $imageProcessor
    ) {}

    public function attach(
        Model $model,
        UploadedFile|array $files,
        string $collection = 'default',
        array $options = []
    ): FileAttachment|array {
        $files = is_array($files) ? $files : [$files];
        $attachments = [];

        foreach ($files as $file) {
            $attachment = $this->processFileUpload($model, $file, $collection, $options);
            $attachments[] = $attachment;
        }

        return count($attachments) === 1 ? $attachments[0] : $attachments;
    }

    public function attachFromUrl(
        Model $model,
        string $url,
        string $collection = 'default',
        array $options = []
    ): FileAttachment {
        // Download file from URL
        $tempPath = tempnam(sys_get_temp_dir(), 'filehub_');
        $contents = file_get_contents($url);
        
        if ($contents === false) {
            throw new FileHubException("Failed to download file from URL: {$url}");
        }

        file_put_contents($tempPath, $contents);

        // Create temporary uploaded file object
        $originalName = basename(parse_url($url, PHP_URL_PATH)) ?: 'downloaded_file';
        $mimeType = mime_content_type($tempPath) ?: 'application/octet-stream';
        
        try {
            $uploadedFile = new UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );

            $attachment = $this->processFileUpload($model, $uploadedFile, $collection, $options);
            
            return $attachment;
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function attachFromPath(
        Model $model,
        string $path,
        string $collection = 'default',
        array $options = []
    ): FileAttachment {
        if (!file_exists($path)) {
            throw new FileHubException("File not found: {$path}");
        }

        $originalName = basename($path);
        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        
        $uploadedFile = new UploadedFile(
            $path,
            $originalName,
            $mimeType,
            null,
            true
        );

        return $this->processFileUpload($model, $uploadedFile, $collection, $options);
    }

    public function detach(Model $model, string|int $attachmentId): bool
    {
        $attachment = $model->fileAttachments()->where('id', $attachmentId)->first();
        
        if (!$attachment) {
            return false;
        }

        return $this->deleteAttachment($attachment);
    }

    public function detachAll(Model $model, string $collection = null): int
    {
        $query = $model->fileAttachments();
        
        if ($collection) {
            $query->where('collection', $collection);
        }

        $attachments = $query->get();
        $deleted = 0;

        foreach ($attachments as $attachment) {
            if ($this->deleteAttachment($attachment)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    public function getAttachments(Model $model, string $collection = null): Collection
    {
        $query = $model->fileAttachments();
        
        if ($collection) {
            $query->where('collection', $collection);
        }

        return $query->get();
    }

    public function getAttachment(Model $model, string|int $attachmentId): ?FileAttachment
    {
        return $model->fileAttachments()->where('id', $attachmentId)->first();
    }

    public function moveToCollection(FileAttachment $attachment, string $newCollection): bool
    {
        $attachment->collection = $newCollection;
        return $attachment->save();
    }

    public function regenerateVariants(FileAttachment $attachment): bool
    {
        if (!$attachment->isImage()) {
            return false;
        }

        try {
            $this->imageProcessor->generateVariants($attachment);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function processFileUpload(
        Model $model,
        UploadedFile $file,
        string $collection,
        array $options = []
    ): FileAttachment {
        // Validate the file
        $this->securityValidator->validate($file, $options);

        // Generate file information
        $fileInfo = $this->generateFileInfo($file, $model, $collection);

        // Store the file
        $path = $this->storeFile($file, $fileInfo);

        // Get user and request information
        $userInfo = $this->getCurrentUserInfo();

        // Create database record
        $attachment = FileAttachment::create([
            'attachable_type' => get_class($model),
            'attachable_id' => $model->getKey(),
            'collection' => $collection,
            'filename' => $fileInfo['filename'],
            'original_filename' => $file->getClientOriginalName(),
            'path' => $path,
            'disk' => $fileInfo['disk'],
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'file_hash' => $fileInfo['hash'],
            'metadata' => $this->extractMetadata($file),
            'uploaded_by' => $userInfo['user_id'],
            'uploaded_by_type' => $userInfo['user_type'],
            'upload_ip_address' => $userInfo['ip_address'],
            'upload_user_agent' => $userInfo['user_agent'],
        ]);

        // Process image variants if applicable
        if ($attachment->isImage() && config('filehub.queue.enabled', false)) {
            ProcessImageVariants::dispatch($attachment);
        } elseif ($attachment->isImage()) {
            $this->imageProcessor->generateVariants($attachment);
        }

        // Fire event
        event(new FileAttached($attachment));

        return $attachment;
    }

    private function generateFileInfo(UploadedFile $file, Model $model, string $collection): array
    {
        $disk = config('filehub.default_disk', 'public');
        $hash = hash_file('sha256', $file->getPathname());
        
        $filename = config('filehub.security.hash_filenames', true)
            ? $hash . '.' . $file->getClientOriginalExtension()
            : Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();

        return [
            'disk' => $disk,
            'filename' => $filename,
            'hash' => $hash,
            'directory' => $this->generateDirectory($model, $collection),
        ];
    }

    private function generateDirectory(Model $model, string $collection): string
    {
        $structure = config('filehub.organization.directory_structure', 'model/id/collection');

        return match ($structure) {
            'model/id/collection' => strtolower(class_basename($model)) . '/' . $model->getKey() . '/' . $collection,
            'date' => now()->format(config('filehub.organization.date_format', 'Y/m/d')),
            default => $collection
        };
    }

    private function storeFile(UploadedFile $file, array $fileInfo): string
    {
        $directory = $fileInfo['directory'];
        $filename = $fileInfo['filename'];
        
        return $file->storeAs(
            $directory,
            $filename,
            ['disk' => $fileInfo['disk']]
        );
    }

    private function extractMetadata(UploadedFile $file): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];

        // Extract image dimensions if it's an image
        if ($this->fileTypeDetector->isImage($file)) {
            try {
                $imageSize = getimagesize($file->getPathname());
                if ($imageSize) {
                    $metadata['dimensions'] = [
                        'width' => $imageSize[0],
                        'height' => $imageSize[1],
                        'aspect_ratio' => round($imageSize[0] / $imageSize[1], 2),
                    ];
                }
            } catch (\Exception $e) {
                // Ignore if we can't get dimensions
            }
        }

        return $metadata;
    }

    private function getCurrentUserInfo(): array
    {
        $request = app(Request::class);
        
        $user = Auth::user();
        
        return [
            'user_id' => $user?->getKey(),
            'user_type' => $user ? get_class($user) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];
    }

    private function deleteAttachment(FileAttachment $attachment): bool
    {
        try {
            // Collect variant paths for cleanup
            $variantPaths = [];
            if ($attachment->hasVariants()) {
                foreach ($attachment->getVariants() as $variant) {
                    if (isset($variant['path'])) {
                        $variantPaths[] = $variant['path'];
                    }
                }
            }

            // Delete the database record
            $attachment->delete();

            // Queue file cleanup
            if (config('filehub.queue.enabled', false)) {
                CleanupFile::dispatch($attachment->disk, $attachment->path, $variantPaths);
            } else {
                // Delete files immediately
                Storage::disk($attachment->disk)->delete($attachment->path);
                Storage::disk($attachment->disk)->delete($variantPaths);
            }

            // Fire event
            event(new FileDetached($attachment));

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
