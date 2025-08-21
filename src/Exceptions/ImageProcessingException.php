<?php

namespace Litepie\FileHub\Exceptions;

use Exception;

class ImageProcessingException extends Exception
{
    public static function variantGenerationFailed(string $variant, string $reason): self
    {
        return new self("Failed to generate variant '{$variant}': {$reason}");
    }

    public static function invalidImageFormat(string $format): self
    {
        return new self("Invalid image format: {$format}");
    }
}
