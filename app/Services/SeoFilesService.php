<?php

namespace App\Services;

use App\Support\OmrConfig;
use App\Support\Omr\OmrCachedClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeoFilesService
{
    public function __construct(private readonly TenantCanonicalUrlResolver $canonicalUrls) {}

    private const FILES = [
        'robots' => [
            'filename' => 'robots.txt',
            'mime_type' => 'text/plain; charset=UTF-8',
        ],
        'humans' => [
            'filename' => 'humans.txt',
            'mime_type' => 'text/plain; charset=UTF-8',
        ],
        'llms' => [
            'filename' => 'llms.txt',
            'mime_type' => 'text/plain; charset=UTF-8',
        ],
        'manifest' => [
            'filename' => 'manifest.webmanifest',
            'mime_type' => 'application/manifest+json; charset=UTF-8',
        ],
        'schema' => [
            'filename' => 'schema.json',
            'mime_type' => 'application/ld+json; charset=UTF-8',
        ],
        'security' => [
            'filename' => 'security.txt',
            'mime_type' => 'text/plain; charset=UTF-8',
        ],
        'identity' => [
            'filename' => 'identity.json',
            'mime_type' => 'application/json; charset=UTF-8',
        ],
        'brand' => [
            'filename' => 'brand.txt',
            'mime_type' => 'text/plain; charset=UTF-8',
        ],
        'knowledge' => [
            'filename' => 'knowledge.json',
            'mime_type' => 'application/json; charset=UTF-8',
        ],
        'ai' => [
            'filename' => 'ai.json',
            'mime_type' => 'application/json; charset=UTF-8',
        ],
        'ai_brand_md' => [
            'filename' => 'ai/brand.md',
            'mime_type' => 'text/markdown; charset=UTF-8',
        ],
        'ai_knowledge_md' => [
            'filename' => 'ai/knowledge.md',
            'mime_type' => 'text/markdown; charset=UTF-8',
        ],
        'ai_about_md' => [
            'filename' => 'ai/about.md',
            'mime_type' => 'text/markdown; charset=UTF-8',
        ],
    ];

    private const JSON_TYPES = [
        'manifest',
        'schema',
        'identity',
        'knowledge',
        'ai',
    ];

    public function sync(string $type, ?string $tenantId = null): ?array
    {
        $type = $this->normalizeType($type);

        if (! isset(self::FILES[$type])) {
            return null;
        }

        $tenantId = $tenantId ?: OmrConfig::tenantId();

        $manifest = $this->manifest($tenantId);

        if ($manifest !== [] && data_get($manifest, 'data.enabled') === false) {
            return null;
        }

        $fileMeta = data_get($manifest, "data.files.{$type}", []);
        $fileMeta = is_array($fileMeta) ? $fileMeta : [];
        $fileMeta = $this->withDerivedAiMarkdownUrl($type, $fileMeta, $manifest);

        $content = $this->normalizeContent(
            $type,
            $this->fetchContent($type, $tenantId, $fileMeta)
        );

        if ($content === null || $content === '') {
            $content = $this->normalizeContent($type, $this->readSyncedFile($type));
        }

        if ($content === null || $content === '') {
            $content = $type === 'robots' ? $this->defaultRobots() : null;
        }

        if ($content === null || $content === '') {
            return null;
        }

        $manifestMimeType = data_get($fileMeta, 'mime_type');

        if (is_array($manifestMimeType)) {
            $manifestMimeType = $manifestMimeType['value'] ?? $manifestMimeType['mime_type'] ?? null;
        }

        $mimeType = is_string($manifestMimeType) && trim($manifestMimeType) !== ''
            ? trim($manifestMimeType)
            : self::FILES[$type]['mime_type'];

        $hash = hash('sha256', $content);
        $paths = $this->syncedPaths($type);
        $changed = false;

        foreach ($paths as $path) {
            if (! is_file($path) || hash_file('sha256', $path) !== $hash) {
                File::ensureDirectoryExists(dirname($path));
                File::put($path, rtrim($content).PHP_EOL);
                $changed = true;
            }
        }

        $this->writeMetadata($type, [
            'tenant' => $tenantId,
            'hash' => $hash,
            'mime_type' => $mimeType,
            'api_url' => OmrConfig::apiUrl("seo-files/{$type}"),
            'updated_at' => now()->toIso8601String(),
            'changed' => $changed,
        ]);

        return [
            'type' => $type,
            'tenant' => $tenantId,
            'content' => rtrim($content).PHP_EOL,
            'mime_type' => $mimeType,
            'paths' => $paths,
            'hash' => $hash,
            'changed' => $changed,
        ];
    }

    public function syncAll(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: OmrConfig::tenantId();
        $results = [];

        foreach (array_keys(self::FILES) as $type) {
            $results[$type] = $this->sync($type, $tenantId);
        }

        return [
            'success' => true,
            'tenant' => $tenantId,
            'files' => $results,
        ];
    }

    private function manifest(string $tenantId): array
    {
        $response = OmrCachedClient::get('seo_files', 'seo-files', [], $tenantId, [
            'success_ttl' => now()->addHours(6),
            'stale_ttl' => now()->addDays(7),
            'failure_ttl' => now()->addMinutes(10),
            'rate_limit_ttl' => now()->addMinutes(15),
        ]);

        return $response['ok'] && is_array($response['json'] ?? null)
            ? $response['json']
            : [];
    }

    private function fetchContent(string $type, string $tenantId, array $fileMeta = []): ?string
    {
        $response = OmrCachedClient::get('seo_files', "seo-files/{$type}", [], $tenantId, [
            'accept' => '*/*',
            'success_ttl' => now()->addHours(6),
            'stale_ttl' => now()->addDays(7),
            'failure_ttl' => now()->addMinutes(10),
            'rate_limit_ttl' => now()->addMinutes(15),
        ]);

        if ($response['ok']) {
            $wrappedContent = data_get($response['json'] ?? [], 'data.content')
                ?? data_get($response['json'] ?? [], 'data.value')
                ?? data_get($response['json'] ?? [], 'content')
                ?? data_get($response['json'] ?? [], 'value');

            if (is_string($wrappedContent)) {
                return $wrappedContent;
            }

            if (is_string($response['body'] ?? null) && $response['body'] !== '') {
                return $response['body'];
            }
        }

        return $this->fetchManifestUrl((string) ($fileMeta['url'] ?? ''), $tenantId, $type);
    }

    private function fetchManifestUrl(string $url, string $tenantId, string $type): ?string
    {
        $url = trim($url);
        $candidateHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $apiHost = strtolower((string) parse_url(OmrConfig::baseUrl(), PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($url === '' || ! in_array($scheme, ['http', 'https'], true) || $candidateHost === '' || $candidateHost !== $apiHost) {
            return null;
        }

        $cacheKey = 'seo_files_public_'.sha1($tenantId.'|'.$url);
        $staleKey = "{$cacheKey}_stale";
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $stale = Cache::get($staleKey);

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->retry(OmrConfig::retryCount(), OmrConfig::retrySleep(), throw: false)
                ->withHeaders([
                    'Accept' => '*/*',
                    'X-Tenant-ID' => $tenantId,
                ])
                ->get($url);

            $content = $response->successful() ? $response->body() : null;

            if (is_string($content) && $content !== '') {
                Cache::put($cacheKey, $content, now()->addHours(6));
                Cache::put($staleKey, $content, now()->addDays(7));

                return $content;
            }

            Log::warning('SEO manifest file fetch failed', [
                'type' => $type,
                'tenant' => $tenantId,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('SEO manifest file fetch exception', [
                'type' => $type,
                'tenant' => $tenantId,
                'error' => $e->getMessage(),
            ]);
        }

        return is_string($stale) && $stale !== '' ? $stale : null;
    }

    private function readSyncedFile(string $type): ?string
    {
        $path = $this->syncedPath(self::FILES[$type]['filename']);

        return is_file($path) ? File::get($path) : null;
    }

    private function normalizeContent(string $type, ?string $content): ?string
    {
        if ($content === null || trim($content) === '') {
            return null;
        }

        if (! in_array($type, self::JSON_TYPES, true)) {
            return $content;
        }

        $content = ltrim($content, "\xEF\xBB\xBF\x00\t\n\r ");
        $decoded = $this->decodeJson($content);

        if ($decoded !== null) {
            return rtrim($content);
        }

        $document = $this->firstJsonDocument($content);
        $decoded = $document === null ? null : $this->decodeJson($document);

        if ($decoded === null) {
            Log::warning('Invalid JSON SEO file content was rejected', [
                'type' => $type,
                'tenant' => OmrConfig::tenantId(),
            ]);

            return null;
        }

        return rtrim($document);
    }

    private function withDerivedAiMarkdownUrl(string $type, array $fileMeta, array $manifest): array
    {
        $filenames = [
            'ai_brand_md' => 'brand.md',
            'ai_knowledge_md' => 'knowledge.md',
            'ai_about_md' => 'about.md',
        ];

        if (! isset($filenames[$type]) || trim((string) ($fileMeta['url'] ?? '')) !== '') {
            return $fileMeta;
        }

        $aiUrl = trim((string) (
            data_get($manifest, 'data.files.ai.url')
            ?: data_get($manifest, 'data.paths.ai')
        ));

        if ($aiUrl === '' || ! preg_match('#/ai\.json(?:\?.*)?$#', $aiUrl)) {
            return $fileMeta;
        }

        $fileMeta['url'] = preg_replace(
            '#/ai\.json(?:\?.*)?$#',
            '/ai/'.$filenames[$type],
            $aiUrl
        );

        return $fileMeta;
    }

    private function decodeJson(string $content): mixed
    {
        try {
            return json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }

    private function firstJsonDocument(string $content): ?string
    {
        $start = null;
        $stack = [];
        $inString = false;
        $escaped = false;
        $length = strlen($content);

        for ($index = 0; $index < $length; $index++) {
            $character = $content[$index];

            if ($start === null) {
                if ($character !== '{' && $character !== '[') {
                    continue;
                }

                $start = $index;
                $stack[] = $character;

                continue;
            }

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($character === '"') {
                $inString = true;

                continue;
            }

            if ($character === '{' || $character === '[') {
                $stack[] = $character;

                continue;
            }

            if ($character !== '}' && $character !== ']') {
                continue;
            }

            $opening = array_pop($stack);
            $matches = ($opening === '{' && $character === '}')
                || ($opening === '[' && $character === ']');

            if (! $matches) {
                return null;
            }

            if ($stack === []) {
                return substr($content, $start, $index - $start + 1);
            }
        }

        return null;
    }

    private function syncedPaths(string $type): array
    {
        $paths = [
            $this->syncedPath(self::FILES[$type]['filename']),
        ];

        if ($type === 'security') {
            $paths[] = $this->syncedPath('.well-known/security.txt');
        }

        if ($type === 'ai') {
            $paths[] = $this->syncedPath('.well-known/ai.json');
        }

        if ($type === 'manifest') {
            $paths[] = $this->syncedPath('site.webmanifest');
        }

        return $paths;
    }

    private function syncedPath(string $path): string
    {
        return storage_path('app/seo-files/public/'.ltrim($path, '/'));
    }

    private function writeMetadata(string $type, array $metadata): void
    {
        $path = storage_path("app/seo-files/{$type}.json");

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            'robots.txt' => 'robots',
            'site-manifest', 'site.webmanifest', 'webmanifest' => 'manifest',
            'well-known-security', 'security-well-known' => 'security',
            'well-known-ai', 'ai-well-known', '.well-known/ai.json' => 'ai',
            default => $type,
        };
    }

    private function defaultRobots(): string
    {
        $baseUrl = $this->canonicalUrls->baseUrl();

        return $baseUrl
            ? "User-agent: *\nAllow: /\n\nSitemap: {$baseUrl}/sitemap.xml"
            : "User-agent: *\nAllow: /";
    }
}
