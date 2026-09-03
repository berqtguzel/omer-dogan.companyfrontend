<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class MediaMirrorService
{
    private Filesystem $disk;

    private string $cacheDir;

    private string $manifestPath;

    /** @var array<string, array<string, mixed>> */
    private array $manifest = [];

    private bool $manifestLoaded = false;

    /** @var list<string> */
    private array $mediaKeys = [
        'image',
        'video_url',
        'video_poster',
        'og_image',
        'favicon',
        'logo',
        'dark_logo',
        'logo_dark',
        'icon',
        'thumbnail',
        'poster',
        'src',
    ];

    public function __construct()
    {
        $diskName = (string) config('media_mirror.disk', 'public');
        $this->disk = Storage::disk($diskName);
        $this->cacheDir = trim((string) config('media_mirror.cache_dir', 'media-cache'), '/');
        $this->manifestPath = (string) config('media_mirror.manifest_file', 'media-mirror/manifest.json');
    }

    public function enabled(): bool
    {
        return (bool) config('media_mirror.enabled', true);
    }

    public function transform(mixed $data, bool $sync = false): mixed
    {
        if (! $this->enabled()) {
            return $data;
        }

        if (is_string($data)) {
            return $this->mirrorIfUrl($data, $sync) ?? $data;
        }

        if (! is_array($data)) {
            return $data;
        }

        $result = [];

        foreach ($data as $key => $value) {
            if (is_string($value) && $this->shouldMirrorField($key, $value)) {
                $variant = $this->variantForField((string) $key);
                $result[$key] = $this->mirrorIfUrl($value, $sync, $variant) ?? $value;

                continue;
            }

            if (is_array($value) && $key === 'media' && isset($value['url']) && is_string($value['url'])) {
                $value['url'] = $this->mirrorIfUrl($value['url'], $sync, 'card') ?? $value['url'];
            }

            $result[$key] = $this->transform($value, $sync);
        }

        return $result;
    }

    /** @return array{downloaded:int, skipped:int, failed:int, total:int} */
    public function syncAllFromSiteData(string $locale = 'de'): array
    {
        $urls = $this->collectUrlsFromSiteData($locale);
        $stats = ['downloaded' => 0, 'skipped' => 0, 'failed' => 0, 'total' => count($urls)];

        foreach ($urls as $url) {
            $before = $this->manifest[$url]['source_hash'] ?? null;
            $local = $this->syncUrl($url);

            if (! $local) {
                $stats['failed']++;

                continue;
            }

            $after = $this->manifest[$url]['source_hash'] ?? null;

            if ($before === $after && $before !== null) {
                $stats['skipped']++;
            } else {
                $stats['downloaded']++;
            }
        }

        return $stats;
    }

    /** @return list<string> */
    public function collectUrlsFromSiteData(string $locale = 'de'): array
    {
        $tenantId = \App\Support\OmrConfig::tenantId();
        $urls = [];

        $sources = [
            \App\Http\Controllers\CategoryController::getCategories($locale, false),
            \App\Http\Controllers\WidgetController::getWidgets($tenantId, $locale, false),
            \App\Http\Controllers\SliderController::getSliders($tenantId, $locale, false),
            \App\Http\Controllers\SettingsController::getSettings($tenantId, $locale, false),
            // Static page hero images used by .sp-hero live inside the pages
            // payload and were previously missing from the mirror manifest.
            \App\Http\Controllers\StaticPageController::getPages($locale),
        ];

        foreach ($sources as $source) {
            $this->collectUrls($source, $urls);
        }

        return array_values(array_unique($urls));
    }

    public function mirrorIfUrl(string $url, bool $sync = false, string $variant = 'default'): ?string
    {
        if (! $this->enabled() || ! $this->isRemoteMediaUrl($url)) {
            return $url;
        }

        $normalized = $this->normalizeUrl($url);
        $this->loadManifest();

        if ($sync) {
            return $this->syncUrl($normalized, false, $variant);
        }

        $path = $this->resolveVariantPath($normalized, $variant);

        if ($path && $this->disk->exists($path)) {
            return $this->publicUrl($path);
        }

        // The upstream app versions filenames whenever media is re-saved.
        // While that host is unavailable, keep serving the newest cached file
        // from the same logical image family instead of exposing a broken
        // remote URL to the browser.
        $fallbackPath = $this->resolveFamilyFallbackPath($normalized, $variant);

        if ($fallbackPath) {
            return $this->publicUrl($fallbackPath);
        }

        if (! config('media_mirror.download_on_request', false)) {
            return $normalized;
        }

        return $this->proxyUrl($normalized) ?? $url;
    }

    public function proxyUrl(string $url): ?string
    {
        $normalized = $this->normalizeUrl($url);

        if (! $this->isRemoteMediaUrl($normalized)) {
            return null;
        }

        $remoteHost = strtolower((string) parse_url($normalized, PHP_URL_HOST));
        $currentHost = strtolower((string) request()->getHost());

        if ($remoteHost !== '' && $remoteHost === $currentHost) {
            return $normalized;
        }

        $token = rtrim(strtr(base64_encode($normalized), '+/', '-_'), '=');
        $proxyPath = trim((string) config('media_mirror.proxy_path', 'media-proxy'), '/');

        return rtrim(request()->getSchemeAndHttpHost(), '/')."/{$proxyPath}/{$token}";
    }

    public function proxySourceUrl(string $token, array $query = []): ?string
    {
        if ($token === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $token)) {
            return null;
        }

        $base64 = strtr($token, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);
        $decoded = base64_decode($base64, true);

        if (! is_string($decoded) || ! $this->isRemoteMediaUrl($decoded)) {
            return null;
        }

        $allowedQuery = array_intersect_key($query, array_flip([
            'format', 'w', 'h', 'q', 'width', 'height', 'quality', 'fit',
        ]));

        if ($allowedQuery === []) {
            return $decoded;
        }

        $separator = str_contains($decoded, '?') ? '&' : '?';

        return $decoded.$separator.http_build_query($allowedQuery);
    }

    public function syncUrl(string $url, bool $force = false, string $preferredVariant = 'default'): ?string
    {
        if (! $this->isRemoteMediaUrl($url)) {
            return $url;
        }

        $normalized = $this->normalizeUrl($url);
        $this->loadManifest();

        $existing = $this->manifest[$normalized] ?? null;

        // Web requests must never revalidate an already cached asset against
        // the remote host. Explicit sync commands can use $force when needed.
        if (! $force && $existing && $this->entryExists($existing)) {
            return $this->publicUrl(
                $this->resolveVariantPath($normalized, $preferredVariant) ?? $existing['path']
            );
        }

        $failureKey = 'media_mirror_failure_'.sha1($normalized);

        if (! $force && Cache::has($failureKey)) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->get($normalized);

            if (! $response->successful()) {
                Cache::put($failureKey, true, now()->addMinutes(10));

                Log::warning('Media mirror download failed', [
                    'url' => $normalized,
                    'status' => $response->status(),
                ]);

                $fallback = $this->resolveVariantPath($normalized, $preferredVariant);

                $fallback ??= $this->resolveFamilyFallbackPath($normalized, $preferredVariant);

                return $fallback ? $this->publicUrl($fallback) : null;
            }

            $body = $response->body();
            $ext = $this->guessExtension($normalized, $response->header('Content-Type'));
            $sourceHash = md5($body);
            $remoteMeta = [
                'etag' => $response->header('ETag'),
                'size' => $response->hasHeader('Content-Length')
                    ? (int) $response->header('Content-Length')
                    : strlen($body),
                'last_modified' => $response->header('Last-Modified'),
            ];

            Cache::forget($failureKey);

            if (
                ! $force
                && $existing
                && ($existing['source_hash'] ?? '') === $sourceHash
                && $this->entryExists($existing)
            ) {
                $this->manifest[$normalized]['synced_at'] = now()->toIso8601String();
                $this->saveManifest();

                return $this->publicUrl(
                    $this->resolveVariantPath($normalized, $preferredVariant) ?? $existing['path']
                );
            }

            if ($this->isVideoExtension($ext)) {
                return $this->storeSingleFile($normalized, $body, $ext, $remoteMeta, $existing);
            }

            return $this->storeImageVariants($normalized, $body, $ext, $remoteMeta, $existing, $preferredVariant);
        } catch (\Throwable $e) {
            Cache::put($failureKey, true, now()->addMinutes(10));

            Log::error('Media mirror exception', [
                'url' => $normalized,
                'error' => $e->getMessage(),
            ]);

            $fallback = $this->resolveVariantPath($normalized, $preferredVariant);

            $fallback ??= $this->resolveFamilyFallbackPath($normalized, $preferredVariant);

            return $fallback ? $this->publicUrl($fallback) : null;
        }
    }

    /** @return array{processed:int, skipped:int, failed:int, total:int} */
    public function regenerateMissingVariants(bool $force = false): array
    {
        $this->loadManifest();

        $stats = ['processed' => 0, 'skipped' => 0, 'failed' => 0, 'total' => count($this->manifest)];

        foreach ($this->manifest as $normalized => $entry) {
            if (! $force && isset($entry['variants']) && $entry['variants'] !== [] && $this->entryExists($entry)) {
                $stats['skipped']++;

                continue;
            }

            $path = $entry['path'] ?? null;

            if (! is_string($path) || ! $this->disk->exists($path)) {
                $stats['failed']++;

                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if ($this->isVideoExtension($ext) || $ext === 'svg') {
                $stats['skipped']++;

                continue;
            }

            try {
                $body = $this->disk->get($path);
                $result = $this->storeImageVariants($normalized, $body, $ext, null, $entry, 'default');

                if ($result) {
                    $stats['processed']++;
                } else {
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                Log::error('Media variant regeneration failed', [
                    'url' => $normalized,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public function ensureVariant(string $url, string $variant = 'card'): ?string
    {
        if (! $this->isRemoteMediaUrl($url)) {
            return null;
        }

        $normalized = $this->normalizeUrl($url);
        $this->loadManifest();
        $entry = $this->manifest[$normalized] ?? null;

        if (! is_array($entry) || ! $this->entryExists($entry)) {
            $fallbackPath = $this->resolveFamilyFallbackPath($normalized, $variant);

            return $fallbackPath ? $this->publicUrl($fallbackPath) : null;
        }

        $existingVariant = $entry['variants'][$variant]['path'] ?? null;

        if (is_string($existingVariant) && $this->disk->exists($existingVariant)) {
            return $this->publicUrl($existingVariant);
        }

        $sourcePath = $entry['path'] ?? null;
        $maxWidth = (int) config("media_mirror.variants.{$variant}", 0);

        if (! is_string($sourcePath) || ! $this->disk->exists($sourcePath) || $maxWidth < 1) {
            return null;
        }

        $body = $this->disk->get($sourcePath);
        $encoded = $this->encodeImageBodyVariant($body, $maxWidth);

        if ($encoded === null) {
            return null;
        }

        $hash = md5($encoded);
        $path = $this->buildStoragePath($hash, 'webp');
        $this->disk->put($path, $encoded);

        $this->manifest[$normalized]['variants'][$variant] = [
            'path' => $path,
            'hash' => $hash,
            'size' => strlen($encoded),
            'width' => $maxWidth,
        ];
        $this->manifest[$normalized]['synced_at'] = now()->toIso8601String();
        $this->saveManifest();

        return $this->publicUrl($path);
    }

    public function pruneOrphans(): int
    {
        $this->loadManifest();

        $referenced = [];

        foreach ($this->manifest as $entry) {
            if (isset($entry['path']) && is_string($entry['path'])) {
                $referenced[] = $entry['path'];
            }

            foreach ($entry['variants'] ?? [] as $variant) {
                if (isset($variant['path']) && is_string($variant['path'])) {
                    $referenced[] = $variant['path'];
                }
            }
        }

        $referenced = array_unique($referenced);
        $deleted = 0;

        foreach ($this->disk->allFiles($this->cacheDir) as $file) {
            if (! in_array($file, $referenced, true)) {
                $this->disk->delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function rewriteHtml(string $html, bool $sync = false): string
    {
        if (! $this->enabled()) {
            return $html;
        }

        return (string) preg_replace_callback(
            '#https?://[^\s"\'<>]+#i',
            function (array $matches) use ($sync) {
                $url = html_entity_decode($matches[0], ENT_QUOTES);

                if (! $this->isRemoteMediaUrl($url)) {
                    return $matches[0];
                }

                return $this->mirrorIfUrl($url, $sync, 'default') ?? $matches[0];
            },
            $html
        );
    }

    private function variantForField(string $field): string
    {
        $cardFields = config('media_mirror.card_fields', ['image']);

        return in_array($field, $cardFields, true) ? 'card' : 'default';
    }

    private function resolveVariantPath(string $normalizedUrl, string $variant): ?string
    {
        $entry = $this->manifest[$normalizedUrl] ?? null;

        if (! $entry) {
            return null;
        }

        return $entry['variants'][$variant]['path']
            ?? $entry['path']
            ?? null;
    }

    private function resolveFamilyFallbackPath(string $normalizedUrl, string $variant): ?string
    {
        $family = $this->mediaFamily($normalizedUrl);

        if ($family === null) {
            return null;
        }

        $matches = [];

        foreach ($this->manifest as $sourceUrl => $entry) {
            if (
                ! is_array($entry)
                || $this->mediaFamily((string) $sourceUrl) !== $family
                || ! $this->entryExists($entry)
            ) {
                continue;
            }

            $path = $entry['variants'][$variant]['path'] ?? $entry['path'] ?? null;

            if (is_string($path) && $this->disk->exists($path)) {
                $matches[] = [
                    'path' => $path,
                    'synced_at' => (string) ($entry['synced_at'] ?? ''),
                ];
            }
        }

        if ($matches === []) {
            return null;
        }

        usort(
            $matches,
            fn (array $left, array $right) => strcmp($right['synced_at'], $left['synced_at'])
        );

        return $matches[0]['path'];
    }

    private function mediaFamily(string $url): ?string
    {
        $path = parse_url(html_entity_decode($url, ENT_QUOTES), PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return null;
        }

        $name = strtolower((string) pathinfo($path, PATHINFO_FILENAME));
        $family = preg_replace('/_(?:[a-f0-9]{8,}|\d{8,})$/i', '', $name) ?? $name;
        $family = trim($family, '-_');

        return strlen($family) >= 5 ? $family : null;
    }

    /** @param array<string, mixed> $entry */
    private function entryExists(array $entry): bool
    {
        if (isset($entry['path']) && $this->disk->exists($entry['path'])) {
            return true;
        }

        foreach ($entry['variants'] ?? [] as $variant) {
            if (isset($variant['path']) && $this->disk->exists($variant['path'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|null  $remoteMeta
     * @param  array<string, mixed>|null  $existing
     */
    private function storeImageVariants(
        string $normalized,
        string $body,
        string $ext,
        ?array $remoteMeta,
        ?array $existing,
        string $preferredVariant
    ): ?string {
        $image = function_exists('imagecreatefromstring')
            ? @\imagecreatefromstring($body)
            : false;

        $variantsConfig = config('media_mirror.variants', [
            'card' => 480,
            'default' => 1200,
        ]);

        $storedVariants = [];
        $primaryPath = null;

        foreach ($variantsConfig as $name => $maxWidth) {
            $encoded = $image instanceof \GdImage
                ? $this->encodeImageVariant($image, (int) $maxWidth)
                : $this->encodeImageVariantWithFfmpeg($body, (int) $maxWidth);

            if ($encoded === null) {
                continue;
            }

            $hash = md5($encoded);
            $path = $this->buildStoragePath($hash, 'webp');
            $this->disk->put($path, $encoded);

            $storedVariants[$name] = [
                'path' => $path,
                'hash' => $hash,
                'size' => strlen($encoded),
                'width' => (int) $maxWidth,
            ];

            if ($name === 'default' || $primaryPath === null) {
                $primaryPath = $path;
            }
        }

        if ($image instanceof \GdImage) {
            \imagedestroy($image);
        }

        if ($storedVariants === []) {
            return $this->storeSingleFile($normalized, $body, $ext, $remoteMeta, $existing);
        }

        $this->deleteEntryFiles($existing);

        $this->manifest[$normalized] = [
            'path' => $storedVariants['default']['path'] ?? $primaryPath,
            'source_hash' => md5($body),
            'variants' => $storedVariants,
            'etag' => $remoteMeta['etag'] ?? null,
            'remote_modified' => $remoteMeta['last_modified'] ?? null,
            'synced_at' => now()->toIso8601String(),
        ];
        $this->saveManifest();

        $usePath = $this->resolveVariantPath($normalized, $preferredVariant) ?? $primaryPath;

        return $usePath ? $this->publicUrl($usePath) : null;
    }

    /**
     * @param  array<string, mixed>|null  $remoteMeta
     * @param  array<string, mixed>|null  $existing
     */
    private function storeSingleFile(
        string $normalized,
        string $body,
        string $ext,
        ?array $remoteMeta,
        ?array $existing
    ): ?string {
        $hash = md5($body);
        $path = $this->buildStoragePath($hash, $ext);

        $this->deleteEntryFiles($existing);
        $this->disk->put($path, $body);

        $this->manifest[$normalized] = [
            'path' => $path,
            'hash' => $hash,
            'source_hash' => $hash,
            'size' => strlen($body),
            'etag' => $remoteMeta['etag'] ?? null,
            'remote_modified' => $remoteMeta['last_modified'] ?? null,
            'synced_at' => now()->toIso8601String(),
        ];
        $this->saveManifest();

        return $this->publicUrl($path);
    }

    /** @param  array<string, mixed>|null  $entry */
    private function deleteEntryFiles(?array $entry): void
    {
        if (! $entry) {
            return;
        }

        $paths = [];

        if (isset($entry['path'])) {
            $paths[] = $entry['path'];
        }

        foreach ($entry['variants'] ?? [] as $variant) {
            if (isset($variant['path'])) {
                $paths[] = $variant['path'];
            }
        }

        foreach (array_unique($paths) as $path) {
            if ($this->disk->exists($path)) {
                $this->disk->delete($path);
            }
        }
    }

    private function encodeImageVariant(\GdImage $source, int $maxWidth): ?string
    {
        $width = \imagesx($source);
        $height = \imagesy($source);

        $image = $source;

        if ($width > $maxWidth) {
            $newHeight = max(1, (int) round($height * ($maxWidth / $width)));
            $scaled = \imagescale($source, $maxWidth, $newHeight);

            if ($scaled === false) {
                return null;
            }

            $image = $scaled;
        } else {
            $scaled = null;
        }

        if (! function_exists('imagewebp')) {
            if ($scaled !== null) {
                \imagedestroy($scaled);
            }

            return null;
        }

        ob_start();
        \imagewebp($image, null, (int) config('media_mirror.jpeg_quality', 82));
        $encoded = ob_get_clean() ?: '';

        if ($scaled !== null) {
            \imagedestroy($scaled);
        }

        return $encoded !== '' ? $encoded : null;
    }

    private function encodeImageBodyVariant(string $body, int $maxWidth): ?string
    {
        if (function_exists('imagecreatefromstring')) {
            $image = @\imagecreatefromstring($body);

            if ($image instanceof \GdImage) {
                try {
                    return $this->encodeImageVariant($image, $maxWidth);
                } finally {
                    \imagedestroy($image);
                }
            }
        }

        return $this->encodeImageVariantWithFfmpeg($body, $maxWidth);
    }

    private function encodeImageVariantWithFfmpeg(string $body, int $maxWidth): ?string
    {
        $inputPath = tempnam(sys_get_temp_dir(), 'media-source-');

        if ($inputPath === false) {
            return null;
        }

        $outputPath = $inputPath.'.webp';

        try {
            if (file_put_contents($inputPath, $body) === false) {
                return null;
            }

            $process = new Process([
                'ffmpeg',
                '-y',
                '-v',
                'error',
                '-i',
                $inputPath,
                '-vf',
                "scale='min({$maxWidth},iw)':-2",
                '-frames:v',
                '1',
                '-c:v',
                'libwebp',
                '-q:v',
                (string) config('media_mirror.jpeg_quality', 82),
                '-compression_level',
                '4',
                $outputPath,
            ]);
            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($outputPath)) {
                return null;
            }

            $encoded = file_get_contents($outputPath);

            return is_string($encoded) && $encoded !== '' ? $encoded : null;
        } catch (\Throwable $e) {
            Log::warning('FFmpeg media variant generation failed', [
                'width' => $maxWidth,
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($inputPath);
            @unlink($outputPath);
        }
    }

    /** @param array<string, mixed> $entry */
    private function isUnchanged(array $entry, array $remoteMeta): bool
    {
        if (! empty($remoteMeta['etag']) && ($entry['etag'] ?? '') === $remoteMeta['etag']) {
            return true;
        }

        if (
            isset($remoteMeta['size'], $entry['remote_size'])
            && (int) $remoteMeta['size'] === (int) $entry['remote_size']
            && ! empty($remoteMeta['last_modified'])
            && ($entry['remote_modified'] ?? '') === $remoteMeta['last_modified']
        ) {
            return true;
        }

        return false;
    }

    private function shouldMirrorField(string|int $key, string $value): bool
    {
        if ($this->isRemoteMediaUrl($value)) {
            return true;
        }

        return in_array((string) $key, $this->mediaKeys, true)
            && $this->looksLikeMediaPath($value);
    }

    private function looksLikeMediaPath(string $value): bool
    {
        return str_starts_with($value, '/storage/')
            || str_starts_with($value, 'storage/')
            || (bool) preg_match('#\.(jpe?g|png|gif|webp|svg|mp4|webm|avif)(\?.*)?$#i', $value);
    }

    private function isVideoExtension(string $ext): bool
    {
        return in_array(strtolower($ext), ['mp4', 'webm', 'mov'], true);
    }

    public function isRemoteMediaUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (str_starts_with($url, '/storage/media-cache/')) {
            return false;
        }

        if (str_starts_with($url, '/storage/') || str_starts_with($url, 'storage/')) {
            return ! str_starts_with(ltrim($url, '/'), 'storage/media-cache/');
        }

        if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            return false;
        }

        if (str_contains($url, '/storage/media-cache/')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $hosts = config('media_mirror.remote_hosts', []);
        $remoteBaseHost = parse_url(
            (string) config('media_mirror.remote_base', 'https://omerdogan.de'),
            PHP_URL_HOST
        );

        if (is_string($remoteBaseHost) && $remoteBaseHost !== '') {
            $hosts[] = $remoteBaseHost;
        }

        $hosts = array_unique(array_filter(array_map(
            fn ($allowed) => strtolower(trim((string) $allowed)),
            $hosts
        )));

        foreach ($hosts as $allowed) {
            if (strcasecmp($host, $allowed) === 0 || str_ends_with($host, '.'.$allowed)) {
                return str_contains($url, '/storage/') || (bool) preg_match(
                    '#\.(jpe?g|png|gif|webp|svg|mp4|webm|avif)(\?.*)?$#i',
                    $url
                );
            }
        }

        return false;
    }

    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return rtrim((string) config('media_mirror.remote_base', 'https://omerdogan.de'), '/').$url;
        }

        if (str_starts_with($url, 'storage/')) {
            return rtrim((string) config('media_mirror.remote_base', 'https://omerdogan.de'), '/').'/'.$url;
        }

        return $url;
    }

    /** @param array<string, mixed> $data */
    private function collectUrls(array $data, array &$urls): void
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && $this->shouldMirrorField($key, $value)) {
                $urls[] = $this->normalizeUrl($value);
            } elseif (is_array($value)) {
                $this->collectUrls($value, $urls);
            }
        }
    }

    private function buildStoragePath(string $hash, string $ext): string
    {
        return sprintf(
            '%s/%s/%s/%s.%s',
            $this->cacheDir,
            substr($hash, 0, 2),
            substr($hash, 2, 2),
            $hash,
            $ext
        );
    }

    private function guessExtension(string $url, ?string $contentType): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (is_string($path) && preg_match('/\.([a-z0-9]+)$/i', $path, $matches)) {
            return strtolower($matches[1]);
        }

        return match (true) {
            str_contains((string) $contentType, 'image/webp') => 'webp',
            str_contains((string) $contentType, 'image/png') => 'png',
            str_contains((string) $contentType, 'image/gif') => 'gif',
            str_contains((string) $contentType, 'image/svg') => 'svg',
            str_contains((string) $contentType, 'video/mp4') => 'mp4',
            str_contains((string) $contentType, 'video/webm') => 'webm',
            default => 'jpg',
        };
    }

    public function publicUrl(string $path): string
    {
        $path = ltrim($path, '/');
        $cachePrefix = $this->cacheDir.'/';

        if (str_starts_with($path, $cachePrefix)) {
            return '/media-cache/'.substr($path, strlen($cachePrefix));
        }

        return '/storage/'.$path;
    }

    private function loadManifest(): void
    {
        if ($this->manifestLoaded) {
            return;
        }

        $disk = Storage::disk('local');

        if ($disk->exists($this->manifestPath)) {
            $json = $disk->get($this->manifestPath);
            $decoded = json_decode($json, true);
            $this->manifest = is_array($decoded) ? $decoded : [];
        }

        $this->manifestLoaded = true;
    }

    private function saveManifest(): void
    {
        Storage::disk('local')->put(
            $this->manifestPath,
            json_encode($this->manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
