<?php

namespace Litepie\FileHub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Services\ImageProcessor;
use Litepie\FileHub\Events\FileProcessed;

class ProcessImageVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout;
    public int $tries = 3;

    public function __construct(
        public FileAttachment $attachment
    ) {
        $this->timeout = config('filehub.queue.timeout', 300);
        $this->onQueue(config('filehub.queue.queue', 'filehub'));
    }

    public function handle(ImageProcessor $processor): void
    {
        if (!$this->attachment->exists()) {
            $this->fail('Original file no longer exists');
            return;
        }

        try {
            $variants = $processor->generateVariants($this->attachment);
            
            if (!empty($variants)) {
                event(new FileProcessed($this->attachment));
            }
        } catch (\Exception $e) {
            \Log::error('Failed to process image variants: ' . $e->getMessage(), [
                'attachment_id' => $this->attachment->id,
                'file_path' => $this->attachment->path,
            ]);
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        \Log::error('Image variant processing failed permanently: ' . $exception->getMessage(), [
            'attachment_id' => $this->attachment->id,
            'file_path' => $this->attachment->path,
        ]);
    }
}
