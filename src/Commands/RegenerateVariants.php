<?php

namespace Litepie\FileHub\Commands;

use Illuminate\Console\Command;
use Litepie\FileHub\Models\FileAttachment;
use Litepie\FileHub\Services\ImageProcessor;
use Litepie\FileHub\Jobs\ProcessImageVariants;

class RegenerateVariants extends Command
{
    protected $signature = 'filehub:regenerate-variants
                            {--collection= : Only process files in this collection}
                            {--queue : Process variants in queue}
                            {--force : Regenerate even if variants already exist}';

    protected $description = 'Regenerate image variants for file attachments';

    public function handle(ImageProcessor $processor): int
    {
        $collection = $this->option('collection');
        $useQueue = $this->option('queue');
        $force = $this->option('force');

        $query = FileAttachment::where('mime_type', 'like', 'image/%');

        if ($collection) {
            $query->where('collection', $collection);
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('variants')
                  ->orWhere('variants', '[]')
                  ->orWhere('variants', '{}');
            });
        }

        $attachments = $query->get();
        $total = $attachments->count();

        if ($total === 0) {
            $this->info('No image attachments found to process.');
            return 0;
        }

        $this->info("Processing {$total} image attachments...");

        $progressBar = $this->output->createProgressBar($total);
        $processed = 0;

        foreach ($attachments as $attachment) {
            try {
                if ($useQueue) {
                    ProcessImageVariants::dispatch($attachment);
                } else {
                    $processor->generateVariants($attachment);
                }
                
                $processed++;
            } catch (\Exception $e) {
                $this->error("Failed to process attachment {$attachment->id}: " . $e->getMessage());
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $action = $useQueue ? 'queued' : 'processed';
        $this->info("Successfully {$action} {$processed} image attachments.");

        return 0;
    }
}
