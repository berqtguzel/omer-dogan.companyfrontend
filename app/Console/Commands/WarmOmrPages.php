<?php

namespace App\Console\Commands;

use App\Http\Controllers\StaticPageController;
use Illuminate\Console\Command;

class WarmOmrPages extends Command
{
    protected $signature = 'omr:warm-pages
        {--locale=de : Source locale whose records contain all translations}
        {--all-locales : Seed every supported web locale from the source payload}
        {--force : Forget the source page-list cache before warming}';

    protected $description = 'Warm static pages once and seed locale caches without repeating API requests';

    public function handle(): int
    {
        $result = StaticPageController::warmPages(
            strtolower((string) $this->option('locale')),
            (bool) $this->option('all-locales'),
            (bool) $this->option('force'),
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Pages', $result['count']],
                ['Locales', implode(', ', $result['locales'])],
                ['Source', $result['locale']],
            ],
        );

        if (! $result['ok']) {
            $this->error('Page warmup failed or returned no pages.');

            return self::FAILURE;
        }

        $this->info('OMR page caches are ready.');

        return self::SUCCESS;
    }
}
