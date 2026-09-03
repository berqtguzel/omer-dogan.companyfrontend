<?php

namespace App\Console\Commands;

use App\Services\TenantCanonicalUrlResolver;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ValidateSitemaps extends Command
{
    protected $signature = 'sitemap:validate
        {--base-url= : Staging or production base URL; defaults to tenant canonical URL}
        {--limit=0 : Maximum number of page URLs to check; 0 checks all}
        {--concurrency=12 : Parallel page requests}
        {--timeout=20 : Timeout per request in seconds}
        {--skip-page-html : Check page status only, without canonical/noindex/hreflang HTML checks}';

    protected $description = 'Read-only validation of sitemap XML, child sitemap availability and page HTTP/SEO state';

    private array $errors = [];
    private array $sitemaps = [];
    private array $pageUrls = [];
    private array $seenPageUrls = [];
    private int $duplicateUrls = 0;

    public function handle(TenantCanonicalUrlResolver $resolver): int
    {
        $configuredBase = trim((string) $this->option('base-url'));
        $baseUrl = $configuredBase !== ''
            ? $this->normalizeBaseUrl($configuredBase)
            : $resolver->baseUrl();

        if (! $baseUrl) {
            $this->error('Canonical/base URL is missing. Set TENANT_CANONICAL_URL or pass --base-url.');

            return self::FAILURE;
        }

        $rootUrl = $baseUrl.'/sitemap.xml';
        $this->info("Validating {$rootUrl}");

        $this->collectSitemap($rootUrl, $baseUrl, 0);

        $limit = max(0, (int) $this->option('limit'));
        $urls = array_keys($this->pageUrls);
        if ($limit > 0) {
            $urls = array_slice($urls, 0, $limit);
        }

        $this->checkPages($urls, $baseUrl);
        $this->renderReport($baseUrl, count($urls), $limit > 0 && count($this->pageUrls) > $limit);

        return $this->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function collectSitemap(string $url, string $baseUrl, int $depth): void
    {
        if ($depth > 4 || isset($this->sitemaps[$url])) {
            return;
        }

        $this->sitemaps[$url] = true;
        $response = $this->get($url, 'application/xml,text/xml');

        if (! $response) {
            return;
        }

        if ($response->status() !== 200) {
            $this->addError('SITEMAP_HTTP', $url, 'HTTP '.$response->status().$this->redirectDetail($response));

            return;
        }

        $xml = $this->parseXml($response->body(), $url);
        if (! $xml) {
            return;
        }

        $rootName = strtolower($xml->documentElement?->localName ?? '');
        $xpath = new DOMXPath($xml);

        if ($rootName === 'sitemapindex') {
            $locations = $xpath->query('//*[local-name()="sitemap"]/*[local-name()="loc"]');
            if (! $locations || $locations->length === 0) {
                $this->addError('EMPTY_INDEX', $url, 'Sitemap index contains no child sitemap.');

                return;
            }

            foreach ($locations as $location) {
                $child = trim($location->textContent);
                if (! $this->isAllowedUrl($child, $baseUrl)) {
                    $this->addError('SITEMAP_DOMAIN', $child ?: $url, 'Child sitemap is not on the canonical domain or is not HTTPS.');
                    continue;
                }
                $this->collectSitemap($child, $baseUrl, $depth + 1);
            }

            return;
        }

        if ($rootName !== 'urlset') {
            $this->addError('XML_ROOT', $url, "Unexpected XML root [{$rootName}].");

            return;
        }

        $locations = $xpath->query('//*[local-name()="url"]/*[local-name()="loc"]');
        if (! $locations || $locations->length === 0) {
            $this->addError('EMPTY_SITEMAP', $url, 'URL sitemap contains no URLs.');

            return;
        }

        foreach ($locations as $location) {
            $pageUrl = trim($location->textContent);
            if (! $this->isAllowedUrl($pageUrl, $baseUrl)) {
                $this->addError('URL_DOMAIN', $pageUrl ?: $url, 'URL is not on the canonical domain, is not HTTPS, or contains query/fragment/public.');
                continue;
            }
            if (isset($this->pageUrls[$pageUrl])) {
                $this->duplicateUrls++;
                $this->addError('DUPLICATE_URL', $pageUrl, 'URL occurs more than once in sitemap files.');
                continue;
            }
            $this->pageUrls[$pageUrl] = $url;
        }
    }

    private function checkPages(array $urls, string $baseUrl): void
    {
        if ($urls === []) {
            $this->addError('NO_PAGES', $baseUrl.'/sitemap.xml', 'No valid page URLs were discovered.');

            return;
        }

        $concurrency = min(30, max(1, (int) $this->option('concurrency')));
        $this->output->progressStart(count($urls));

        foreach (array_chunk($urls, $concurrency) as $chunk) {
            try {
                $responses = Http::pool(function (Pool $pool) use ($chunk) {
                    $requests = [];
                    foreach ($chunk as $url) {
                        $requests[] = $pool->as(sha1($url))
                            ->withOptions(['allow_redirects' => false, 'verify' => ! app()->environment('local', 'testing')])
                            ->connectTimeout(5)
                            ->timeout(20)
                            ->withHeaders(['User-Agent' => 'SitemapPreflight/1.0', 'Accept' => 'text/html,application/xhtml+xml'])
                            ->get($url);
                    }

                    return $requests;
                });
            } catch (\Throwable $e) {
                foreach ($chunk as $url) {
                    $this->addError('PAGE_CONNECTION', $url, $e->getMessage());
                    $this->output->progressAdvance();
                }
                continue;
            }

            foreach ($chunk as $url) {
                $result = $responses[sha1($url)] ?? null;
                if (! $result instanceof Response) {
                    $this->addError('PAGE_CONNECTION', $url, $result instanceof \Throwable ? $result->getMessage() : 'No HTTP response.');
                    $this->output->progressAdvance();
                    continue;
                }

                $this->seenPageUrls[$url] = true;
                if ($result->status() !== 200) {
                    $this->addError('PAGE_HTTP', $url, 'HTTP '.$result->status().$this->redirectDetail($result));
                    $this->output->progressAdvance();
                    continue;
                }

                if (! $this->option('skip-page-html')) {
                    $this->inspectHtml($url, $result->body(), $baseUrl);
                }
                $this->output->progressAdvance();
            }
        }

        $this->output->progressFinish();
    }

    private function inspectHtml(string $url, string $html, string $baseUrl): void
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $this->addError('PAGE_HTML', $url, 'Response is not parseable HTML.');

            return;
        }

        $xpath = new DOMXPath($document);
        $canonicals = $xpath->query('//link[contains(concat(" ", translate(@rel, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), " "), " canonical ")]');
        $count = $canonicals?->length ?? 0;

        if ($count !== 1) {
            $this->addError('CANONICAL_COUNT', $url, "Expected one canonical, found {$count}.");
        } else {
            $canonical = trim((string) $canonicals->item(0)?->getAttribute('href'));
            if ($this->comparableUrl($canonical) !== $this->comparableUrl($url)) {
                $this->addError('CANONICAL_MISMATCH', $url, "Canonical is [{$canonical}].");
            }
        }

        $robots = $xpath->query('//meta[translate(@name, "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="robots"]');
        foreach ($robots ?: [] as $meta) {
            if (str_contains(strtolower($meta->getAttribute('content')), 'noindex')) {
                $this->addError('NOINDEX', $url, 'Sitemap URL has a noindex robots directive.');
            }
        }

        $alternates = $xpath->query('//link[@hreflang and @href]');
        foreach ($alternates ?: [] as $alternate) {
            $href = trim($alternate->getAttribute('href'));
            if (! $this->isAllowedUrl($href, $baseUrl)) {
                $this->addError('HREFLANG_DOMAIN', $url, "Invalid hreflang URL [{$href}].");
            }
        }
    }

    private function get(string $url, string $accept): ?Response
    {
        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'verify' => ! app()->environment('local', 'testing'),
            ])->connectTimeout(5)
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'SitemapPreflight/1.0', 'Accept' => $accept])
                ->get($url);
        } catch (\Throwable $e) {
            $this->addError('CONNECTION', $url, $e->getMessage());

            return null;
        }
    }

    private function parseXml(string $contents, string $url): ?DOMDocument
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        $messages = collect(libxml_get_errors())->map(fn ($error) => trim($error->message))->filter()->unique()->implode('; ');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            $this->addError('INVALID_XML', $url, $messages ?: 'XML could not be parsed.');

            return null;
        }

        return $document;
    }

    private function isAllowedUrl(string $url, string $baseUrl): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $expectedHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $host === $expectedHost
            && ($scheme === 'https' || str_contains($expectedHost, 'localhost'))
            && parse_url($url, PHP_URL_QUERY) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null
            && ! preg_match('#^/public(?:/|$)#i', $path);
    }

    private function comparableUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $path = preg_replace('#/+#', '/', '/'.ltrim((string) ($parts['path'] ?? '/'), '/')) ?: '/';
        if (! preg_match('#^/[a-z]{2}/$#i', $path)) {
            $path = rtrim($path, '/') ?: '/';
        }

        return strtolower((string) ($parts['scheme'] ?? '')).'://'.strtolower($parts['host']).$path;
    }

    private function normalizeBaseUrl(string $url): ?string
    {
        $url = rtrim(trim($url), '/');

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function redirectDetail(Response $response): string
    {
        $location = $response->header('Location');

        return $location ? " -> {$location}" : '';
    }

    private function timeout(): int
    {
        return min(60, max(2, (int) $this->option('timeout')));
    }

    private function addError(string $type, string $url, string $detail): void
    {
        $this->errors[] = compact('type', 'url', 'detail');
    }

    private function renderReport(string $baseUrl, int $checkedPages, bool $limited): void
    {
        $this->newLine();
        $this->table(['Metric', 'Count'], [
            ['Sitemaps checked', count($this->sitemaps)],
            ['URLs discovered', count($this->pageUrls)],
            ['Pages checked', $checkedPages],
            ['Duplicate URLs', $this->duplicateUrls],
            ['Errors', count($this->errors)],
        ]);

        if ($limited) {
            $this->warn('The --limit option was used; this was not a complete URL audit.');
        }

        if ($this->errors !== []) {
            $this->newLine();
            $this->error('Validation failed. First 100 problems:');
            $this->table(['Type', 'URL', 'Detail'], array_map(
                fn ($error) => [$error['type'], $error['url'], $error['detail']],
                array_slice($this->errors, 0, 100)
            ));
            if (count($this->errors) > 100) {
                $this->warn((count($this->errors) - 100).' additional errors were omitted from console output.');
            }

            return;
        }

        $this->info("PASS: {$baseUrl} sitemap tree and every checked page passed.");
    }
}
