<?php

namespace Litepie\FileHub\Exceptions;

use Exception;

class ValidationException extends Exception
{
    public static function fileTooLarge(int $size, int $maxSize): self
    {
        return new self("File size ({$size} bytes) exceeds maximum allowed size ({$maxSize} bytes)");
    }

    public static function invalidMimeType(string $mimeType): self
    {
        return new self("MIME type not allowed: {$mimeType}");
    }

    public static function invalidExtension(string $extension): self
    {
        return new self("File extension not allowed: {$extension}");
    }
}
