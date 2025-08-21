<?php

namespace Litepie\FileHub\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Litepie\FileHub\Models\FileAttachment;

class FileAttached
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public FileAttachment $attachment
    ) {}
}
