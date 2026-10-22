<?php

namespace App\Console\Commands;

use App\Helpers\WatermarkHelper;
use Illuminate\Console\Command;

class RegenerateWatermarks extends Command
{
    protected $signature   = 'watermark:regenerate';
    protected $description = 'Regenerate all watermarked images using their stored originals and current settings.';

    public function handle(): int
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $this->info('Starting watermark regeneration — this will re-apply current watermark settings to all property images...');
        
        WatermarkHelper::regenerateAll();
        
        $this->info('Watermark regeneration completed successfully.');
        return self::SUCCESS;
    }
}
