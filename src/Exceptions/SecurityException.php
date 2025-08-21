<?php

namespace Litepie\FileHub\Exceptions;

use Exception;

class SecurityException extends Exception
{
    public static function suspiciousFile(string $reason): self
    {
        return new self("Security violation: {$reason}");
    }

    public static function forbiddenFileType(string $mimeType): self
    {
        return new self("File type not allowed: {$mimeType}");
    }

    public static function malwareDetected(): self
    {
        return new self("Malware detected in uploaded file");
    }
}
