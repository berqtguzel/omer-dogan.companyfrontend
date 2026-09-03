<?php

namespace App\Console\Commands;

use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Console\Command;

class WarmOmrContent extends Command
{
    protected $signature = 'omr:warm-content
        {--locale=de : Locale to warm}
        {--page=1 : Blog page to warm}
        {--with-category-posts : Also warm category-specific blog post lists}';

    protected $description = 'Warm OMR content endpoints such as blog posts, blog categories, and sliders';

    public function handle(): int
    {
        $locale = strtolower((string) $this->option('locale'));
        $page = max(1, (int) $this->option('page'));
        $tenant = OmrConfig::tenantId();
        $mainTenant = OmrConfig::mainTenantId() ?: $tenant;

        if ($tenant === '') {
            $this->error('OMR tenant is missing.');

            return self::FAILURE;
        }

        $this->info("Warming OMR content for tenant={$tenant}, locale={$locale}");

        $categoriesResponse = $this->get('blog', 'blog-posts/categories', [
            'locale' => $locale,
            'lang' => $locale,
        ], $tenant);

        $this->line('blog-posts/categories '.(($categoriesResponse['ok'] ?? false) ? 'ok' : 'failed'));

        $postsResponse = $this->get('blog', 'blog-posts', [
            'locale' => $locale,
            'lang' => $locale,
            'page' => $page,
        ], $tenant);

        $this->line('blog-posts '.(($postsResponse['ok'] ?? false) ? 'ok' : 'failed'));

        if ($this->option('with-category-posts')) {
            $categories = collect(data_get($categoriesResponse, 'json.data', []))
                ->map(fn ($category) => is_array($category) ? ($category['slug'] ?? $category['category_slug'] ?? null) : null)
                ->filter()
                ->unique()
                ->values();

            foreach ($categories as $slug) {
                $response = $this->get('blog', 'blog-posts/categories/'.rawurlencode((string) $slug), [
                    'locale' => $locale,
                    'lang' => $locale,
                    'category' => $slug,
                    'page' => $page,
                ], $tenant);

                $this->line("blog-posts/categories/{$slug} ".(($response['ok'] ?? false) ? 'ok' : 'failed'));

                if ((int) ($response['status'] ?? 0) === 429) {
                    $this->warn('Rate limited; stopping category blog warm early.');

                    return self::SUCCESS;
                }
            }
        }

        $slidersResponse = $this->get('sliders', 'sliders', [
            'locale' => $locale,
            'lang' => $locale,
        ], $mainTenant);

        $this->line('sliders '.(($slidersResponse['ok'] ?? false) ? 'ok' : 'failed'));
        $this->info('OMR content warm complete.');

        return self::SUCCESS;
    }

    private function get(string $namespace, string $path, array $query, string $tenant): array
    {
        return OmrCachedClient::get($namespace, $path, $query, $tenant, [
            'connect_timeout' => 3,
            'timeout' => 8,
            'success_ttl' => now()->addDays(30),
            'stale_ttl' => now()->addDays(120),
            'failure_ttl' => now()->addMinutes(10),
            'rate_limit_ttl' => now()->addMinutes(30),
            'cooldown_minutes' => 30,
        ]);
    }
}
