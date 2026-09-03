<?php

namespace App\Console\Commands;

use App\Services\DashboardService;
use Illuminate\Console\Command;

class WarmCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-dashboard';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm up dashboard API cache';

    /**
     * Execute the console command.
     */
    public function handle(DashboardService $dashboardService)
    {
        $this->info('Warming up dashboard cache...');

        try {
            $dashboardService->getContent('home');
            $this->info('Content cached');

            $dashboardService->getLocations();
            $this->info('Locations cached');

            $dashboardService->getSettings();
            $this->info('Settings cached');

            $this->info('Cache warmed up successfully!');
        } catch (\Exception $e) {
            $this->error('Error warming cache: '.$e->getMessage());

            return 1;
        }

        return 0;
    }
}
