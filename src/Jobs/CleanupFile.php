<?php

namespace Litepie\FileHub\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class CleanupFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $disk,
        public string $path,
        public array $variantPaths = []
    ) {
        $this->onQueue(config('filehub.queue.queue', 'filehub'));
    }

    public function handle(): void
    {
        // Delete main file
        Storage::disk($this->disk)->delete($this->path);

        // Delete variants
        foreach ($this->variantPaths as $variantPath) {
            Storage::disk($this->disk)->delete($variantPath);
        }
    }
}
