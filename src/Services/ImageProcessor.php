<?php

namespace Litepie\FileHub\Services;

use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Illuminate\Support\Facades\Storage;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Exceptions\ImageProcessingException;

class ImageProcessor
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $driver = config('filehub.image_processing.driver', 'gd') === 'imagick' && extension_loaded('imagick')
            ? new ImagickDriver()
            : new GdDriver();

        $this->imageManager = new ImageManager($driver);
    }

    /**
     * Generate image variants for a file attachment
     */
    public function generateVariants(FileAttachment $attachment): array
    {
        if (!$attachment->isImage()) {
            return [];
        }

        if (!$attachment->exists()) {
            throw new ImageProcessingException('Original file does not exist');
        }

        $variants = [];
        $variantConfigs = config('filehub.image_processing.variants', []);

        if (empty($variantConfigs)) {
            return [];
        }

        try {
            $image = $this->imageManager->read($attachment->getContents());

            // Apply auto-orient if configured
            if (config('filehub.image_processing.auto_orient', true)) {
                // Auto-orient is automatic in Intervention Image 3.x
            }

            // Strip metadata if configured
            if (config('filehub.image_processing.strip_metadata', true)) {
                // Note: Intervention Image 3.x doesn't preserve EXIF by default
            }

            foreach ($variantConfigs as $name => $config) {
                try {
                    $variant = $this->generateVariant($image, $attachment, $name, $config);
                    if ($variant) {
                        $variants[$name] = $variant;
                    }
                } catch (\Exception $e) {
                    \Log::error("Failed to generate variant '{$name}': " . $e->getMessage(), [
                        'attachment_id' => $attachment->id,
                        'variant' => $name,
                    ]);
                }
            }

            // Update attachment with variants
            $attachment->variants = $variants;
            $attachment->save();

            return $variants;

        } catch (\Exception $e) {
            throw new ImageProcessingException(
                "Failed to generate variants: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Generate a single image variant
     */
    private function generateVariant($image, FileAttachment $attachment, string $name, array $config): ?array
    {
        $width = $config['width'] ?? null;
        $height = $config['height'] ?? null;
        $method = $config['method'] ?? 'resize';
        $quality = $config['quality'] ?? config('filehub.image_processing.quality', 85);

        if (!$width && !$height) {
            return null;
        }

        // Clone the image to avoid modifying the original
        $variantImage = clone $image;

        // Apply the transformation based on method
        switch ($method) {
            case 'crop':
                $variantImage = $this->cropImage($variantImage, $width, $height);
                break;
            case 'resize':
                $variantImage = $this->resizeImage($variantImage, $width, $height);
                break;
            case 'fit':
                $variantImage = $this->fitImage($variantImage, $width, $height);
                break;
            default:
                $variantImage = $this->resizeImage($variantImage, $width, $height);
        }

        // Generate variant path
        $pathInfo = pathinfo($attachment->path);
        $variantPath = $pathInfo['dirname'] . '/' . 
                       $pathInfo['filename'] . '_' . $name . '.' . 
                       $pathInfo['extension'];

        // Encode the image
        $encoded = $this->encodeImage($variantImage, $attachment->mime_type, $quality);

        // Store the variant
        Storage::disk($attachment->disk)->put($variantPath, $encoded);

        // Get variant size
        $variantSize = strlen($encoded);

        return [
            'path' => $variantPath,
            'size' => $variantSize,
            'width' => $variantImage->width(),
            'height' => $variantImage->height(),
            'mime_type' => $attachment->mime_type,
        ];
    }

    /**
     * Resize image maintaining aspect ratio
     */
    private function resizeImage($image, ?int $width, ?int $height)
    {
        if ($width && $height) {
            $image->scale($width, $height);
        } elseif ($width) {
            $image->scaleDown($width);
        } elseif ($height) {
            $image->scaleDown(height: $height);
        }

        return $image;
    }

    /**
     * Crop image to exact dimensions
     */
    private function cropImage($image, ?int $width, ?int $height)
    {
        if (!$width || !$height) {
            return $this->resizeImage($image, $width, $height);
        }

        $image->cover($width, $height);
        return $image;
    }

    /**
     * Fit image within dimensions with letterbox/pillarbox
     */
    private function fitImage($image, ?int $width, ?int $height)
    {
        if (!$width || !$height) {
            return $this->resizeImage($image, $width, $height);
        }

        $image->contain($width, $height);
        return $image;
    }

    /**
     * Encode image to specified format
     */
    private function encodeImage($image, string $mimeType, int $quality): string
    {
        $format = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return $image->encode(format: $format, quality: $quality)->toString();
    }

    /**
     * Process and optimize image
     */
    public function processImage(FileAttachment $attachment): FileAttachment
    {
        if (!$attachment->isImage()) {
            return $attachment;
        }

        if (!$attachment->exists()) {
            throw new ImageProcessingException('Original file does not exist');
        }

        try {
            $image = $this->imageManager->read($attachment->getContents());

            // Get image dimensions
            $metadata = $attachment->metadata ?? [];
            $metadata['dimensions'] = [
                'width' => $image->width(),
                'height' => $image->height(),
                'aspect_ratio' => round($image->width() / $image->height(), 4),
            ];

            // Strip metadata if configured
            if (config('filehub.image_processing.strip_metadata', true)) {
                $quality = config('filehub.image_processing.quality', 85);
                $encoded = $this->encodeImage($image, $attachment->mime_type, $quality);
                Storage::disk($attachment->disk)->put($attachment->path, $encoded);
                $attachment->size = strlen($encoded);
            }

            $attachment->metadata = $metadata;
            $attachment->save();

            return $attachment;

        } catch (\Exception $e) {
            throw new ImageProcessingException(
                "Failed to process image: " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Delete all variants for an attachment
     */
    public function deleteVariants(FileAttachment $attachment): void
    {
        if (!$attachment->hasVariants()) {
            return;
        }

        $disk = Storage::disk($attachment->disk);

        foreach ($attachment->getVariants() as $variant) {
            if (isset($variant['path']) && $disk->exists($variant['path'])) {
                $disk->delete($variant['path']);
            }
        }
    }

    /**
     * Check if image processing is available
     */
    public function isAvailable(): bool
    {
        $driver = config('filehub.image_processing.driver', 'gd');
        
        return match ($driver) {
            'imagick' => extension_loaded('imagick'),
            'gd' => extension_loaded('gd'),
            default => extension_loaded('gd'),
        };
    }

    /**
     * Get image information
     */
    public function getImageInfo(FileAttachment $attachment): array
    {
        if (!$attachment->isImage() || !$attachment->exists()) {
            return [];
        }

        try {
            $image = $this->imageManager->read($attachment->getContents());

            return [
                'width' => $image->width(),
                'height' => $image->height(),
                'aspect_ratio' => round($image->width() / $image->height(), 4),
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
