<?php

namespace Litepie\FileHub\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Collection;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Facades\FileHub;

trait HasFileAttachments
{
    public function fileAttachments(): MorphMany
    {
        return $this->morphMany(FileAttachment::class, 'attachable');
    }

    public function attachFile(
        UploadedFile|array $files,
        string $collection = 'default',
        array $options = []
    ): FileAttachment|array {
        return FileHub::attach($this, $files, $collection, $options);
    }

    public function attachFromUrl(
        string $url,
        string $collection = 'default',
        array $options = []
    ): FileAttachment {
        return FileHub::attachFromUrl($this, $url, $collection, $options);
    }

    public function attachFromPath(
        string $path,
        string $collection = 'default',
        array $options = []
    ): FileAttachment {
        return FileHub::attachFromPath($this, $path, $collection, $options);
    }

    public function detachFile(string|int $attachmentId): bool
    {
        return FileHub::detach($this, $attachmentId);
    }

    public function detachAllFiles(string $collection = null): int
    {
        return FileHub::detachAll($this, $collection);
    }

    public function getFileAttachment(string|int $attachmentId): ?FileAttachment
    {
        return FileHub::getAttachment($this, $attachmentId);
    }

    public function getFileAttachments(string $collection = null): Collection
    {
        return FileHub::getAttachments($this, $collection);
    }

    public function getFirstFileAttachment(string $collection = 'default'): ?FileAttachment
    {
        return $this->fileAttachments()
            ->where('collection', $collection)
            ->orderBy('created_at', 'asc')
            ->first();
    }

    public function getLatestFileAttachment(string $collection = 'default'): ?FileAttachment
    {
        return $this->fileAttachments()
            ->where('collection', $collection)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    public function hasFileAttachments(string $collection = null): bool
    {
        $query = $this->fileAttachments();
        
        if ($collection) {
            $query->where('collection', $collection);
        }

        return $query->exists();
    }

    public function getFileAttachmentsCount(string $collection = null): int
    {
        $query = $this->fileAttachments();
        
        if ($collection) {
            $query->where('collection', $collection);
        }

        return $query->count();
    }

    public function moveFileToCollection(string|int $attachmentId, string $newCollection): bool
    {
        $attachment = $this->getFileAttachment($attachmentId);
        
        if (!$attachment) {
            return false;
        }

        return FileHub::moveToCollection($attachment, $newCollection);
    }

    public function regenerateFileVariants(string|int $attachmentId): bool
    {
        $attachment = $this->getFileAttachment($attachmentId);
        
        if (!$attachment) {
            return false;
        }

        return FileHub::regenerateVariants($attachment);
    }

    public static function bootHasFileAttachments(): void
    {
        static::deleting(function ($model) {
            // Only delete files when force deleting or if not using soft deletes
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->detachAllFiles();
        });
    }
}
