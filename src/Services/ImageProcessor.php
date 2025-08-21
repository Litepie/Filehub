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

    // ...existing code for all public and private methods as in your context...
}
