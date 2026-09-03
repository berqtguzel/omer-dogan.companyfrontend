<?php

namespace App\Services;

use App\Http\Controllers\SettingsController;
use App\Support\MediaUrl;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FaviconSyncService
{
    public function __construct(private readonly MediaMirrorService $mediaMirror) {}

    /** @return array{ok:bool,changed:bool,message:string,path:?string} */
    public function sync(?string $locale = null, bool $force = false): array
    {
        $tenant = OmrConfig::tenantId();
        $locale = strtolower($locale ?: OmrConfig::defaultLocale());
        $settings = SettingsController::getFrontendSettings($tenant, $locale, false);
        $branding = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];
        $value = $branding['site_favicon_url']
            ?? $settings['site_favicon_url']
            ?? $branding['favicon_url']
            ?? $branding['site_favicon']
            ?? $settings['site_favicon']
            ?? $branding['favicon']
            ?? null;
        $url = MediaUrl::resolve($value);

        if (! is_string($url) || $url === '') {
            return $this->failed('API settings do not contain a favicon.');
        }

        $metadataPath = (string) config('favicon.metadata_path', storage_path('app/favicon-sync.json'));
        $previous = $this->previousSync($url, $metadataPath);

        if (! $force && $previous !== null) {
            return ['ok' => true, 'changed' => false, 'message' => 'unchanged', 'path' => $previous['path']];
        }

        $body = $this->readMirroredFile($url);

        if (! is_string($body) || $body === '') {
            try {
                $localUrl = $this->mediaMirror->syncUrl($url, $force, 'default');
                $body = $this->readMirroredFile($localUrl);
            } catch (\Throwable) {
                $body = null;
            }
        }

        if (! is_string($body) || $body === '') {
            $body = $this->downloadRemoteImage($url);
        }

        $extension = is_string($body) ? $this->imageExtension($body) : null;

        if (! is_string($body) || $body === '' || $extension === null) {
            return $this->failed('The API favicon could not be downloaded as an image.');
        }

        $basePath = (string) config('favicon.public_base_path', public_path('favicon'));

        if ($basePath === '') {
            return $this->failed('The public favicon path is empty.');
        }

        $target = $basePath.'.'.$extension;

        if (! $force && is_file($target) && hash_file('sha256', $target) === hash('sha256', $body)) {
            $this->writeMetadata($metadataPath, $url, $body, $target);

            return ['ok' => true, 'changed' => false, 'message' => 'unchanged', 'path' => $target];
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            return $this->failed('The public favicon directory could not be created.');
        }

        if (@file_put_contents($target, $body, LOCK_EX) === false) {
            return $this->failed(basename($target).' could not be written.');
        }

        @chmod($target, 0644);
        $this->writeMetadata($metadataPath, $url, $body, $target);

        return ['ok' => true, 'changed' => true, 'message' => 'synced', 'path' => $target];
    }

    /** @return array{path:string}|null */
    private function previousSync(string $url, string $metadataPath): ?array
    {
        if (! is_file($metadataPath)) {
            return null;
        }

        $metadata = json_decode((string) @file_get_contents($metadataPath), true);
        $target = is_array($metadata) && is_string($metadata['path'] ?? null)
            ? $metadata['path']
            : null;

        return $target !== null
            && is_file($target)
            && ($metadata['source'] ?? null) === $url
            && is_string($metadata['sha256'] ?? null)
            && hash_file('sha256', $target) === $metadata['sha256']
                ? ['path' => $target]
                : null;
    }

    private function writeMetadata(string $metadataPath, string $url, string $body, string $target): void
    {
        $directory = dirname($metadataPath);

        if (! is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        @file_put_contents($metadataPath, json_encode([
            'source' => $url,
            'path' => $target,
            'public_url' => '/'.basename($target),
            'sha256' => hash('sha256', $body),
            'synced_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function readMirroredFile(?string $localUrl): ?string
    {
        if (! is_string($localUrl) || $localUrl === '') {
            return null;
        }

        $path = parse_url($localUrl, PHP_URL_PATH) ?: $localUrl;
        $path = ltrim($path, '/');

        foreach (['storage/media-cache/', 'media-cache/'] as $prefix) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            $relative = trim((string) config('media_mirror.cache_dir', 'media-cache'), '/')
                .'/'.substr($path, strlen($prefix));
            $disk = Storage::disk((string) config('media_mirror.disk', 'public'));

            return $disk->exists($relative) ? $disk->get($relative) : null;
        }

        return null;
    }

    private function downloadRemoteImage(string $url): ?string
    {
        if (! preg_match('#^https?://#i', $url)) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()
                ->connectTimeout(5)
                ->timeout(20)
                ->get($url);

            return $response->successful() ? $response->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function imageExtension(string $body): ?string
    {
        return match (true) {
            str_starts_with($body, "\x00\x00\x01\x00") => 'ico',
            str_starts_with($body, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($body, "\xff\xd8\xff") => 'jpg',
            str_starts_with($body, 'GIF87a'), str_starts_with($body, 'GIF89a') => 'gif',
            str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP' => 'webp',
            preg_match('/^\s*<svg\b/i', $body) === 1 => 'svg',
            default => null,
        };
    }

    /** @return array{ok:bool,changed:bool,message:string,path:null} */
    private function failed(string $message): array
    {
        return ['ok' => false, 'changed' => false, 'message' => $message, 'path' => null];
    }
}
