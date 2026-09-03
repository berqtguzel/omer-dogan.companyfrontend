<?php

namespace App\Console\Commands;

use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Console\Command;

class WarmOmrCatalog extends Command
{
    protected $signature = 'omr:warm-catalog
        {--locale=de : Locale to warm}
        {--category= : Comma separated category slugs to warm after serviceCategories}
        {--all-categories : Also warm every root category after serviceCategories}
        {--max-pages=120 : Maximum pages to fetch per category}
        {--per-page=100 : Records per page}
        {--sleep=1100 : Milliseconds to wait between pages}
        {--limit-categories=4 : Maximum missing categories to fetch per run, 0 means unlimited}
        {--force : Fetch again even when the catalog cache is fresh}';

    protected $description = 'Warm the OMR services catalog cache without doing service API calls during web requests';

    public function handle(): int
    {
        $tenant = OmrConfig::tenantForSharedContent();
        $locale = strtolower((string) $this->option('locale'));
        $maxPages = max(1, (int) $this->option('max-pages'));
        $perPage = max(1, (int) $this->option('per-page'));
        $sleep = max(0, (int) $this->option('sleep'));
        $limitCategories = max(0, (int) $this->option('limit-categories'));
        $force = (bool) $this->option('force');

        if ($tenant === '') {
            $this->error('OMR tenant is missing.');

            return self::FAILURE;
        }

        $this->info("Warming OMR catalog for tenant={$tenant}, locale={$locale}");

        $rootQuery = [
            'parent_id' => 'null',
            '_max_pages' => 1,
            '_per_page' => $perPage,
            '_sleep_ms' => $sleep,
        ];

        if (! $force && OmrCatalog::hasFreshServices($tenant, $locale, $rootQuery)) {
            $this->line('serviceCategories fresh');
        } else {
            $root = OmrCatalog::warmServices($tenant, $locale, $rootQuery);

            $this->line('serviceCategories '.($root['count'] ?? 0).' '.(($root['ok'] ?? false) ? 'ok' : 'failed'));
        }

        $categorySlugs = $this->categorySlugs($tenant, $locale);
        $fetchedCategories = 0;

        foreach ($categorySlugs as $slug) {
            $categoryQuery = $this->categoryQuery($slug, $maxPages, $perPage, $sleep);

            if (! $force && OmrCatalog::hasFreshServices($tenant, $locale, $categoryQuery)) {
                $this->line("category: {$slug} fresh");

                continue;
            }

            if ($limitCategories > 0 && $fetchedCategories >= $limitCategories) {
                $this->line("category: {$slug} pending");

                continue;
            }

            if (OmrCatalog::isCoolingDown($tenant)) {
                $this->warn("category: {$slug} cooldown; next cron run will retry.");

                return self::SUCCESS;
            }

            $result = OmrCatalog::warmServices($tenant, $locale, $categoryQuery);
            $fetchedCategories++;

            if (! ($result['ok'] ?? false) && (int) ($result['status'] ?? 0) === 429) {
                $this->warn("category: {$slug} rate limited; next cron run will retry.");

                return self::SUCCESS;
            }

            $this->line("category: {$slug} ".($result['count'] ?? 0).' '.(($result['ok'] ?? false) ? 'ok' : 'failed'));
        }

        $this->info('OMR catalog warm complete.');

        return self::SUCCESS;
    }

    private function categoryQuery(string $slug, int $maxPages, int $perPage, int $sleep): array
    {
        $query = match ($slug) {
            'housekeeping-service' => ['category_slug' => 'reinigungsservice'],
            'praxisreinigung' => ['parent_id' => 11],
            default => ['category_slug' => $slug],
        };

        if (! in_array($slug, ['housekeeping-service', 'praxisreinigung'], true)
            && OmrConfig::locationParentId() !== null) {
            $query = ['parent_id' => OmrConfig::locationParentId()];
        }

        return array_merge($query, [
            '_max_pages' => $maxPages,
            '_per_page' => $perPage,
            '_sleep_ms' => $sleep,
        ]);
    }

    private function categorySlugs(string $tenant, string $locale): array
    {
        $configured = (string) (OmrConfig::locationCategorySlug() ?: 'gebaudereinigung');
        $selected = trim((string) $this->option('category'));

        if ($selected !== '') {
            return collect(explode(',', $selected))
                ->map(fn ($slug) => trim($slug))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        if (! $this->option('all-categories')) {
            return [];
        }

        $configuredSlugs = collect(explode(',', $configured))
            ->map(fn ($slug) => trim((string) $slug))
            ->filter();

        return $configuredSlugs
            ->merge(
                collect(OmrCatalog::rootCategories($tenant, $locale))
                    ->map(fn ($category) => $category['slug'] ?? $category['category_slug'] ?? null)
            )
            ->filter()
            ->map(fn ($slug) => trim((string) $slug))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
