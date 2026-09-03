<?php

namespace App\Console\Commands;

use App\Services\SitemapWarmService;
use Illuminate\Console\Command;

class WarmSitemaps extends Command
{
    protected $signature = 'sitemap:warm
        {--locale= : Warm one active web locale and preserve all other active sitemap documents}
        {--force : Ignore the fresh-cache window and fetch the API again}
        {--request-timeout= : Seconds allowed for each API request}
        {--connect-timeout= : Seconds allowed while connecting to the API}
        {--retry-count= : Number of attempts for each API request}
        {--retry-sleep= : Milliseconds between retries}
        {--sleep= : Milliseconds between consecutive sitemap API requests}';

    protected $description = 'Build and atomically activate validated API-backed sitemap XML caches';

    public function handle(SitemapWarmService $sitemaps): int
    {
        $locale = trim((string) $this->option('locale')) ?: null;
        $force = (bool) $this->option('force');
        $this->applyRuntimeOptions();
        $this->info('Building sitemap cache'.($locale ? " for locale [{$locale}]" : ' for all active locales').'...');

        $result = $sitemaps->warm($locale, $force);

        if (! ($result['ok'] ?? false)) {
            $this->error((string) ($result['error'] ?? 'Sitemap warm failed.'));
            $this->warn(($result['stale_preserved'] ?? false)
                ? 'The previous active sitemap version is still being served.'
                : 'No previous active sitemap version exists; the safe bootstrap sitemap remains active.');

            return self::FAILURE;
        }

        if ($result['skipped'] ?? false) {
            $this->info('Sitemap cache is still fresh; use --force to rebuild it.');

            return self::SUCCESS;
        }

        $this->table(['Metric', 'Value'], [
            ['Version', $result['version'] ?? '-'],
            ['Locales', implode(', ', $result['locales'] ?? [])],
            ['Child sitemaps', $result['sitemap_count'] ?? 0],
            ['URLs', $result['url_count'] ?? 0],
            ['API requests', $result['api_requests'] ?? 0],
            ['API pages', $result['api_pages'] ?? 0],
            ['Translations removed', $result['translations_removed'] ?? 0],
            ['Duplicates removed', $result['duplicates_removed'] ?? 0],
            ['Invalid URLs removed', $result['invalid_urls_removed'] ?? 0],
            ['Non-indexable removed', $result['non_indexable_removed'] ?? 0],
            ['Empty modules skipped', $result['empty_modules_skipped'] ?? 0],
        ]);

        foreach ($result['per_locale'] ?? [] as $code => $count) {
            $this->line("{$code}: {$count} URLs");
        }

        $this->info('Validated sitemap version activated atomically.');

        return self::SUCCESS;
    }

    private function applyRuntimeOptions(): void
    {
        $options = [
            'request-timeout' => ['sitemap.request_timeout', 1, 600],
            'connect-timeout' => ['sitemap.connect_timeout', 1, 120],
            'retry-count' => ['sitemap.retry_count', 1, 10],
            'retry-sleep' => ['sitemap.retry_sleep_ms', 0, 60000],
            'sleep' => ['sitemap.request_sleep_ms', 0, 60000],
        ];

        foreach ($options as $option => [$key, $minimum, $maximum]) {
            $value = $this->option($option);

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_numeric($value)) {
                throw new \InvalidArgumentException("--{$option} must be numeric.");
            }

            config([$key => max($minimum, min($maximum, (int) $value))]);
        }
    }
}
