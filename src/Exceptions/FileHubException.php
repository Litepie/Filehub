<?php

namespace Litepie\FileHub\Exceptions;

use Exception;

class FileHubException extends Exception
{
    public static function fileNotFound(string $path): self
    {
        return new self("File not found: {$path}");
    }

    public static function invalidFile(string $reason): self
    {
        return new self("Invalid file: {$reason}");
    }

    public static function uploadFailed(string $reason): self
    {
        return new self("File upload failed: {$reason}");
    }

    public static function processingFailed(string $reason): self
    {
        return new self("File processing failed: {$reason}");
    }
}
