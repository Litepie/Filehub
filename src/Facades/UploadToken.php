<?php

namespace Litepie\FileHub\Facades;

use Illuminate\Support\Facades\Facade;
use Litepie\FileHub\Services\UploadTokenManager;

/**
 * @method static string generateToken(array $data)
 * @method static array|null validateToken(string $token)
 * @method static bool useToken(string $token)
 * @method static bool revokeToken(string $token)
 * @method static string generateSignedUploadUrl(array $parameters = [])
 * @method static bool validateSignedUploadUrl(array $parameters)
 * @method static array|null getTokenInfo(string $token)
 * @method static bool extendToken(string $token, int $additionalSeconds)
 */
class UploadToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UploadTokenManager::class;
    }
}
