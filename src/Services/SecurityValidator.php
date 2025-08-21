<?php

namespace Litepie\FileHub\Services;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Litepie\FileHub\Exceptions\SecurityException;
use Litepie\FileHub\Exceptions\ValidationException;

class SecurityValidator
{
    private MimeTypes $mimeTypes;

    public function __construct()
    {
        $this->mimeTypes = new MimeTypes();
    }

    public function validate(UploadedFile $file, array $options = []): void
    {
        $this->validateFileSize($file, $options);
        $this->validateMimeType($file, $options);
        $this->validateExtension($file, $options);
        $this->validateFilename($file);
        $this->validateFileContents($file);
        
        if (config('filehub.validation.scan_malware')) {
            $this->scanForMalware($file);
        }

        if (config('filehub.security.file_signature_check')) {
            $this->validateFileSignature($file);
        }
    }

    // ...existing code for all private methods as in your context...
}
