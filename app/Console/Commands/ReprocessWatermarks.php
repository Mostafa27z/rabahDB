<?php

namespace App\Console\Commands;

use App\Helpers\WatermarkHelper;
use Illuminate\Console\Command;

class ReprocessWatermarks extends Command
{
    protected $signature   = 'watermark:reprocess';
    protected $description = 'Re-apply watermarks to any property images whose watermarked file is missing on disk.';

    public function handle(): int
    {
        $this->info('Starting watermark reprocess — this may take a while...');
        WatermarkHelper::reprocessMissingWatermarks();
        $this->info('Done! Check the logs for details.');
        return self::SUCCESS;
    }
}
