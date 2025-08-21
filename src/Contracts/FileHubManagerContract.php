<?php

namespace Litepie\FileHub\Contracts;

use Illuminate\Http\UploadedFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Litepie\FileHub\Models\FileAttachment;

interface FileHubManagerContract
{
    public function attach(
        Model $model,
        UploadedFile|array $files,
        string $collection = 'default',
        array $options = []
    ): FileAttachment|array;
    
    public function attachFromUrl(
        Model $model,
        string $url,
        string $collection = 'default',
        array $options = []
    ): FileAttachment;
    
    public function attachFromPath(
        Model $model,
        string $path,
        string $collection = 'default',
        array $options = []
    ): FileAttachment;
    
    public function detach(Model $model, string|int $attachmentId): bool;
    
    public function detachAll(Model $model, string $collection = null): int;
    
    public function getAttachments(Model $model, string $collection = null): Collection;
    
    public function getAttachment(Model $model, string|int $attachmentId): ?FileAttachment;
    
    public function moveToCollection(FileAttachment $attachment, string $newCollection): bool;
    
    public function regenerateVariants(FileAttachment $attachment): bool;
}
