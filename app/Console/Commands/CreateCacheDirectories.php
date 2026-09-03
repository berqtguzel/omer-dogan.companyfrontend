<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateCacheDirectories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:create-directories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create cache directories for images and videos';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating cache directories...');

        try {
            // ⚡ Image cache directories
            $imagePaths = [
                'cache/images/00/00',
                'cache/images/00/01',
                'cache/images/01/00',
                'cache/images/01/01',
            ];

            // ⚡ Video cache directories
            $videoPaths = [
                'cache/videos/00/00',
                'cache/videos/00/01',
                'cache/videos/01/00',
                'cache/videos/01/01',
            ];

            foreach ($imagePaths as $path) {
                if (!Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->makeDirectory($path, 0755, true);
                    $this->info("Created: {$path}");
                }
            }

            foreach ($videoPaths as $path) {
                if (!Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->makeDirectory($path, 0755, true);
                    $this->info("Created: {$path}");
                }
            }

            $this->info('Cache directories created successfully!');
        } catch (\Exception $e) {
            $this->error('Error creating cache directories: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

