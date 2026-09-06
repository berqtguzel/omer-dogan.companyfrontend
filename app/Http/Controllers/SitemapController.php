<?php

namespace App\Http\Controllers;

use App\Exceptions\EmptySitemapException;
use App\Services\CachedSitemapService;
use App\Services\LocalSitemapService;
use App\Services\SitemapLiveService;
use App\Support\LocaleMapper;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class SitemapController extends Controller
{
    public function __construct(
        private readonly CachedSitemapService $cachedSitemaps,
        private readonly SitemapLiveService $liveSitemaps,
        private readonly LocalSitemapService $localSitemaps,
    ) {}

    public function globalIndex(): Response
    {
        if (! config('corporate_home.content_ready')) {
            return $this->unavailableIndex();
        }
        try {
            return $this->serve($this->sitemaps()->index());
        } catch (\Throwable $e) {
            Log::error('Live sitemap index request failed', ['error' => $e->getMessage()]);

            return $this->unavailableIndex();
        }
    }

    public function index(string $locale): Response
    {
        if (! config('corporate_home.content_ready')) {
            return $this->unavailableIndex();
        }
        $locale = LocaleMapper::toWeb($locale);

        if (! LocaleMapper::isSupportedWeb($locale)) {
            return $this->missingUrlset();
        }

        try {
            return $this->serve($this->sitemaps()->index($locale));
        } catch (\Throwable $e) {
            Log::error('Live locale sitemap index request failed', [
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return $this->unavailableIndex();
        }
    }

    public function pages(string $locale): Response
    {
        return $this->module($locale, 'pages');
    }

    public function services(string $locale): Response
    {
        return $this->module($locale, 'services');
    }

    public function locations(string $locale): Response
    {
        return $this->module($locale, 'locations');
    }

    public function serviceLocation(string $locale, int $page): Response
    {
        return $this->module($locale, 'serviceLocation', max(1, $page));
    }

    public function module(string $locale, string $module, ?int $page = null): Response
    {
        if (! config('corporate_home.content_ready')) {
            return $this->unavailableUrlset();
        }
        $locale = LocaleMapper::toWeb($locale);
        [$module, $page] = $this->normalizeModule($module, $page);

        if (! LocaleMapper::isSupportedWeb($locale) || ! $module) {
            return $this->missingUrlset();
        }

        try {
            return $this->serve($this->sitemaps()->module($locale, $module, $page));
        } catch (EmptySitemapException) {
            return $this->missingUrlset();
        } catch (\Throwable $e) {
            Log::error('Live sitemap request failed', [
                'locale' => $locale,
                'module' => $module,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            return $this->unavailableUrlset();
        }
    }

    private function serve(array $document): Response
    {
        $etag = (string) ($document['etag'] ?? '"'.hash('sha256', (string) $document['xml']).'"');
        $headers = [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => $this->sourceName() === 'live'
                ? 'no-store, max-age=0'
                : 'public, max-age='.(int) config('sitemap.http_cache_seconds', 3600),
            'ETag' => $etag,
            'X-Sitemap-Source' => (string) ($document['source'] ?? $this->sourceName()),
        ];

        if (request()->header('If-None-Match') === $etag) {
            return response('', 304, $headers);
        }

        return response((string) $document['xml'], 200, $headers);
    }

    private function sitemaps(): CachedSitemapService|SitemapLiveService|LocalSitemapService
    {
        return match ($this->sourceName()) {
            'live' => $this->liveSitemaps,
            'cache' => $this->cachedSitemaps,
            default => $this->localSitemaps,
        };
    }

    private function sourceName(): string
    {
        $source = strtolower((string) config('sitemap.source', 'local'));

        return in_array($source, ['local', 'cache', 'live'], true) ? $source : 'local';
    }

    private function missingUrlset(): Response
    {
        return response($this->emptyUrlset(), 404, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function unavailableUrlset(): Response
    {
        return response($this->emptyUrlset(), 503, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Retry-After' => '300',
            'X-Sitemap-Source' => 'live-error',
        ]);
    }

    private function unavailableIndex(): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';

        return response($xml, 503, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
            'Retry-After' => '300',
            'X-Sitemap-Source' => 'live-error',
        ]);
    }

    private function emptyUrlset(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    private function normalizeModule(string $module, ?int $page): array
    {
        if ($page === null && preg_match('/^([A-Za-z]+)-(\d+)$/', $module, $matches)) {
            $module = $matches[1];
            $page = max(1, (int) $matches[2]);
        }

        $normalized = strtolower(preg_replace('/[^A-Za-z]/', '', $module) ?: '');

        foreach (config('sitemap.modules', []) as $allowed) {
            if (strtolower($allowed) === $normalized) {
                return [$allowed, $page];
            }
        }

        return [null, null];
    }
}
