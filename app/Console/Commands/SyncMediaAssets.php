<?php

namespace App\Console\Commands;

use App\Services\FaviconSyncService;
use App\Services\MediaMirrorService;
use App\Http\Controllers\CategoryController;
use App\Support\MediaUrl;
use App\Support\OmrConfig;
use Illuminate\Console\Command;

class SyncMediaAssets extends Command
{
    protected $signature = 'media:sync
                            {--locale= : Locale for API data (default: OMR_DEFAULT_LOCALE)}
                            {--force : Re-download even if metadata matches}
                            {--regenerate : Create card/default variants from cached originals}
                            {--cards : Generate 480px WebP variants for category cards}
                            {--prune : Remove files not listed in manifest}';

    protected $description = 'Download remote media to local storage and update when changed';

    public function handle(MediaMirrorService $mirror, FaviconSyncService $faviconSync): int
    {
        if (! $mirror->enabled()) {
            $this->warn('Media mirror is disabled. Set MEDIA_MIRROR_ENABLED=true in .env');

            return self::FAILURE;
        }

        $locale = strtolower((string) ($this->option('locale') ?: OmrConfig::defaultLocale()));
        $force = (bool) $this->option('force');

        $favicon = $faviconSync->sync($locale, $force);

        if ($favicon['ok']) {
            $this->info($favicon['changed']
                ? 'Tenant favicon synced to public/'.basename((string) $favicon['path']).'.'
                : 'Tenant favicon is already up to date.');
        } else {
            $this->warn('Tenant favicon sync skipped: '.$favicon['message']);
        }

        if ($this->option('cards')) {
            $categories = CategoryController::getCategories($locale, false);
            $processed = 0;
            $failed = 0;

            $this->info('Optimizing category card images...');

            foreach ($categories as $category) {
                $url = MediaUrl::resolve($category['image'] ?? null);

                if (! is_string($url) || ! str_starts_with($url, 'http')) {
                    $failed++;
                    continue;
                }

                if ($mirror->ensureVariant($url, 'card')) {
                    $processed++;
                } else {
                    $failed++;
                }
            }

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total', count($categories)],
                    ['Optimized', $processed],
                    ['Failed', $failed],
                ]
            );

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        if ($this->option('regenerate')) {
            $this->info('Regenerating image variants from local cache...');
            $stats = $mirror->regenerateMissingVariants($force);

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total', $stats['total']],
                    ['Processed', $stats['processed']],
                    ['Skipped', $stats['skipped']],
                    ['Failed', $stats['failed']],
                ]
            );

            if ($this->option('prune')) {
                $deleted = $mirror->pruneOrphans();
                $this->info("Pruned {$deleted} orphan file(s).");
            }

            return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->info("Collecting media URLs for locale [{$locale}]...");

        $urls = $mirror->collectUrlsFromSiteData($locale);
        $this->info('Found '.count($urls).' unique media URLs.');

        if ($urls === []) {
            $this->warn('No media URLs found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar(count($urls));
        $bar->start();

        $downloaded = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($urls as $index => $url) {
            $local = $mirror->syncUrl($url, $force);

            if (! $local) {
                $failed++;
            } elseif (
                str_contains($local, '/media-cache/')
                || str_contains($local, '/storage/'.config('media_mirror.cache_dir'))
            ) {
                $downloaded++;
            } else {
                $skipped++;
            }

            $bar->advance();

            // DNS / sunucu yükünü azalt
            if (($index + 1) % 5 === 0) {
                usleep(250_000);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total', count($urls)],
                ['Synced', $downloaded],
                ['Skipped / unchanged', $skipped],
                ['Failed', $failed],
            ]
        );

        $this->info('Media sync finished. Local files are served from /media-cache/ with /storage/media-cache/ fallback.');

        if ($this->option('prune')) {
            $deleted = $mirror->pruneOrphans();
            $this->info("Pruned {$deleted} orphan file(s).");
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
