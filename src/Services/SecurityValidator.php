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

    /**
     * Validate file size
     */
    private function validateFileSize(UploadedFile $file, array $options = []): void
    {
        $maxSize = $options['max_size'] ?? config('filehub.validation.max_size', 10240);
        $fileSizeInKB = $file->getSize() / 1024;

        if ($fileSizeInKB > $maxSize) {
            throw new ValidationException(
                "File size ({$fileSizeInKB} KB) exceeds maximum allowed size ({$maxSize} KB)"
            );
        }
    }

    /**
     * Validate MIME type
     */
    private function validateMimeType(UploadedFile $file, array $options = []): void
    {
        $allowedMimes = $options['allowed_mimes'] ?? config('filehub.validation.allowed_mimes', []);
        
        if (empty($allowedMimes)) {
            return;
        }

        $mimeType = $file->getMimeType();
        
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new ValidationException(
                "File MIME type '{$mimeType}' is not allowed"
            );
        }
    }

    /**
     * Validate file extension
     */
    private function validateExtension(UploadedFile $file, array $options = []): void
    {
        $forbiddenExtensions = config('filehub.validation.forbidden_extensions', []);
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, $forbiddenExtensions, true)) {
            throw new SecurityException(
                "File extension '{$extension}' is forbidden for security reasons"
            );
        }

        // Validate extension matches MIME type if allowed_mimes is set
        if (config('filehub.security.content_type_validation')) {
            $this->validateExtensionMatchesMimeType($file);
        }
    }

    /**
     * Validate that extension matches MIME type
     */
    private function validateExtensionMatchesMimeType(UploadedFile $file): void
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();
        
        $expectedExtensions = $this->mimeTypes->getExtensions($mimeType);
        
        if (!empty($expectedExtensions) && !in_array($extension, $expectedExtensions, true)) {
            throw new SecurityException(
                "File extension '{$extension}' does not match MIME type '{$mimeType}'"
            );
        }
    }

    /**
     * Validate filename for security issues
     */
    private function validateFilename(UploadedFile $file): void
    {
        $filename = $file->getClientOriginalName();

        // Check for path traversal attempts
        if (preg_match('/\.\.[\\/]/', $filename)) {
            throw new SecurityException('Filename contains path traversal attempt');
        }

        // Check for null bytes
        if (str_contains($filename, "\0")) {
            throw new SecurityException('Filename contains null bytes');
        }

        // Check for control characters
        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            throw new SecurityException('Filename contains control characters');
        }
    }

    /**
     * Validate file contents for security issues
     */
    private function validateFileContents(UploadedFile $file): void
    {
        // Check if file is actually an image when it claims to be
        if (config('filehub.validation.check_image_contents')) {
            $mimeType = $file->getMimeType();
            
            if (str_starts_with($mimeType, 'image/')) {
                $this->validateImageContents($file);
            }
        }

        // Check for embedded scripts in files
        $this->checkForEmbeddedScripts($file);
    }

    /**
     * Validate image file contents
     */
    private function validateImageContents(UploadedFile $file): void
    {
        try {
            $imageInfo = @getimagesize($file->getRealPath());
            
            if ($imageInfo === false) {
                throw new SecurityException('File claims to be an image but cannot be verified as such');
            }
        } catch (\Exception $e) {
            throw new SecurityException('Invalid image file: ' . $e->getMessage());
        }
    }

    /**
     * Check for embedded scripts in files
     */
    private function checkForEmbeddedScripts(UploadedFile $file): void
    {
        $content = file_get_contents($file->getRealPath(), false, null, 0, 8192);
        
        // Check for common script patterns
        $dangerousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/onerror=/i',
            '/onload=/i',
            '/<iframe/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                throw new SecurityException('File contains potentially malicious content');
            }
        }
    }

    /**
     * Scan file for malware
     */
    private function scanForMalware(UploadedFile $file): void
    {
        // This is a placeholder for malware scanning integration
        // You would integrate with ClamAV or another virus scanner here
        
        if (!config('filehub.validation.scan_malware')) {
            return;
        }

        // Example integration would go here
        // For now, we'll just check if the file is executable
        if ($this->isExecutable($file)) {
            throw new SecurityException('Executable files are not allowed');
        }
    }

    /**
     * Check if file is executable
     */
    private function isExecutable(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        
        // Check file permissions
        if (is_executable($path)) {
            return true;
        }

        // Check for executable signatures
        $handle = fopen($path, 'rb');
        if ($handle) {
            $bytes = fread($handle, 4);
            fclose($handle);

            // ELF executables
            if (substr($bytes, 0, 4) === "\x7fELF") {
                return true;
            }

            // Windows executables
            if (substr($bytes, 0, 2) === "MZ") {
                return true;
            }

            // Shebang scripts
            if (substr($bytes, 0, 2) === "#!") {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate file signature matches content
     */
    private function validateFileSignature(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        $path = $file->getRealPath();
        
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new SecurityException('Unable to read file for signature validation');
        }

        $bytes = fread($handle, 12);
        fclose($handle);

        // Common file signatures
        $signatures = [
            'image/jpeg' => ["\xFF\xD8\xFF"],
            'image/png' => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
            'image/gif' => ["GIF87a", "GIF89a"],
            'application/pdf' => ["%PDF"],
            'application/zip' => ["PK\x03\x04"],
        ];

        if (isset($signatures[$mimeType])) {
            $validSignature = false;
            
            foreach ($signatures[$mimeType] as $signature) {
                if (str_starts_with($bytes, $signature)) {
                    $validSignature = true;
                    break;
                }
            }

            if (!$validSignature) {
                throw new SecurityException(
                    "File signature does not match declared MIME type '{$mimeType}'"
                );
            }
        }
    }
}
