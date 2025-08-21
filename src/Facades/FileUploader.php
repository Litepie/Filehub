<?php

namespace Litepie\FileHub\Facades;

use Illuminate\Support\Facades\Facade;
use Litepie\FileHub\Services\FileUploaderService;

/**
 * @method static \Illuminate\Database\Eloquent\Collection getFilesByUploader(\Illuminate\Database\Eloquent\Model $user, array $options = [])
 * @method static array getUploaderStats(\Illuminate\Database\Eloquent\Model $user)
 * @method static \Illuminate\Database\Eloquent\Collection getAllUploaders()
 * @method static \Illuminate\Database\Eloquent\Collection getFilesByIpAddress(string $ipAddress)
 * @method static \Illuminate\Database\Eloquent\Collection getRecentUploads(int $limit = 50)
 * @method static array getUploadActivity(string $dateFrom, string $dateTo)
 * @method static \Illuminate\Database\Eloquent\Collection findPotentialDuplicates()
 * @method static array getUserQuotaInfo(\Illuminate\Database\Eloquent\Model $user, array $quotaConfig = [])
 */
class FileUploader extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FileUploaderService::class;
    }
}
