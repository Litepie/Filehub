<?php

namespace Litepie\FileHub\Facades;

use Illuminate\Support\Facades\Facade;
use Litepie\FileHub\Contracts\FileHubManagerContract;

/**
 * @method static \Litepie\FileHub\Models\FileAttachment|array attach(\Illuminate\Database\Eloquent\Model $model, \Illuminate\Http\UploadedFile|array $files, string $collection = 'default', array $options = [])
 * @method static \Litepie\FileHub\Models\FileAttachment attachFromUrl(\Illuminate\Database\Eloquent\Model $model, string $url, string $collection = 'default', array $options = [])
 * @method static \Litepie\FileHub\Models\FileAttachment attachFromPath(\Illuminate\Database\Eloquent\Model $model, string $path, string $collection = 'default', array $options = [])
 * @method static bool detach(\Illuminate\Database\Eloquent\Model $model, string|int $attachmentId)
 * @method static int detachAll(\Illuminate\Database\Eloquent\Model $model, string $collection = null)
 * @method static \Illuminate\Database\Eloquent\Collection getAttachments(\Illuminate\Database\Eloquent\Model $model, string $collection = null)
 * @method static \Litepie\FileHub\Models\FileAttachment|null getAttachment(\Illuminate\Database\Eloquent\Model $model, string|int $attachmentId)
 * @method static bool moveToCollection(\Litepie\FileHub\Models\FileAttachment $attachment, string $newCollection)
 * @method static bool regenerateVariants(\Litepie\FileHub\Models\FileAttachment $attachment)
 */
class FileHub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileHubManagerContract::class;
    }
}
