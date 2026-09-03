<?php

namespace App\Services;

use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

class SitemapCacheStore
{
    public function activeVersion(): ?string
    {
        $version = Cache::get($this->activeKey());

        if (is_string($version) && $version !== '') {
            return $version;
        }

        $snapshot = $this->readJson($this->snapshotRoot().'/active.json');
        $version = is_string($snapshot['version'] ?? null) ? $snapshot['version'] : null;

        if ($version) {
            Cache::forever($this->activeKey(), $version);
        }

        return $version;
    }

    public function metadata(): array
    {
        $version = $this->activeVersion();

        if (! $version) {
            return [];
        }

        $metadata = Cache::get($this->versionKey($version, 'metadata'));

        if (is_array($metadata)) {
            return $metadata;
        }

        $metadata = $this->readJson($this->snapshotRoot()."/{$version}/manifest.json");

        if ($metadata !== []) {
            Cache::forever($this->versionKey($version, 'metadata'), $metadata);
        }

        return $metadata;
    }

    public function document(string $name): ?array
    {
        $version = $this->activeVersion();

        return $version ? $this->versionDocument($version, $name) : null;
    }

    public function documents(): array
    {
        $metadata = $this->metadata();

        return collect($metadata['documents'] ?? [])
            ->mapWithKeys(function ($document, $name) {
                $cached = $this->document((string) $name);

                return $cached ? [(string) $name => $cached] : [];
            })
            ->all();
    }

    public function putBuilding(string $buildId, string $name, array $document): void
    {
        Cache::put(
            $this->buildingKey($buildId, $name),
            $document,
            now()->addHours((int) config('sitemap.building_ttl_hours', 6))
        );
    }

    public function activate(string $buildId, array $documentNames, array $metadata): void
    {
        $documents = [];

        foreach ($documentNames as $name) {
            $document = Cache::get($this->buildingKey($buildId, $name));

            if (! is_array($document) || empty($document['xml'])) {
                throw new RuntimeException("Missing validated building sitemap [{$name}].");
            }

            $documents[$name] = $document;
        }

        $metadata['version'] = $buildId;
        $metadata['documents'] = collect($documents)->map(fn ($document) => [
            'path' => $document['path'] ?? null,
            'count' => $document['count'] ?? 0,
            'locale' => $document['locale'] ?? null,
            'module' => $document['module'] ?? null,
            'lastmod' => $document['lastmod'] ?? null,
            'etag' => $document['etag'] ?? null,
        ])->all();

        $this->writeSnapshot($buildId, $documents, $metadata);

        foreach ($documents as $name => $document) {
            Cache::forever($this->versionKey($buildId, $name), $document);
        }

        Cache::forever($this->versionKey($buildId, 'metadata'), $metadata);

        // This pointer is the only active-state switch. Until this final write,
        // every request continues serving the previous complete version.
        Cache::forever($this->activeKey(), $buildId);
    }

    public function logicalKey(string $locale, string $module, ?int $page = null): string
    {
        $suffix = $module === 'serviceLocation'
            ? 'service-locations'.($page ? ":{$page}" : ':index')
            : $module.($page ? ":{$page}" : '');

        return $this->prefix().":{$locale}:{$suffix}";
    }

    private function versionDocument(string $version, string $name): ?array
    {
        $document = Cache::get($this->versionKey($version, $name));

        if (is_array($document)) {
            return $document;
        }

        $manifest = $this->readJson($this->snapshotRoot()."/{$version}/manifest.json");
        $file = $manifest['files'][$name] ?? null;

        if (! is_string($file)) {
            return null;
        }

        $payload = $this->readJson($this->snapshotRoot()."/{$version}/{$file}");

        if (! is_array($payload) || empty($payload['xml'])) {
            return null;
        }

        Cache::forever($this->versionKey($version, $name), $payload);

        return $payload;
    }

    private function writeSnapshot(string $version, array $documents, array $metadata): void
    {
        $directory = $this->snapshotRoot()."/{$version}";
        File::ensureDirectoryExists($directory);
        $files = [];

        foreach ($documents as $name => $document) {
            $file = sha1($name).'.json';
            $this->atomicWrite($directory."/{$file}", json_encode(
                $document,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ));
            $files[$name] = $file;
        }

        $metadata['files'] = $files;
        $this->atomicWrite($directory.'/manifest.json', json_encode(
            $metadata,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $this->atomicWrite($this->snapshotRoot().'/active.json', json_encode([
            'version' => $version,
            'activated_at' => now()->toAtomString(),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function atomicWrite(string $path, string $contents): void
    {
        File::ensureDirectoryExists(dirname($path));
        $temporary = $path.'.tmp.'.bin2hex(random_bytes(6));
        File::put($temporary, $contents);

        if (! @rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException("Could not atomically write sitemap snapshot [{$path}].");
        }
    }

    private function readJson(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        try {
            $decoded = json_decode((string) File::get($path), true, flags: JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function activeKey(): string
    {
        return $this->prefix().':active-version';
    }

    private function buildingKey(string $version, string $name): string
    {
        return 'sitemap:building:'.$this->tenant().':'.$this->domainScope().":{$version}:".$this->safeName($name);
    }

    private function versionKey(string $version, string $name): string
    {
        return $this->prefix().":version:{$version}:".$this->safeName($name);
    }

    private function prefix(): string
    {
        return 'sitemap:'.$this->tenant().':'.$this->domainScope();
    }

    private function tenant(): string
    {
        return preg_replace('/[^A-Za-z0-9_-]+/', '_', OmrConfig::tenantId()) ?: 'site';
    }

    private function domainScope(): string
    {
        return app(TenantCanonicalUrlResolver::class)->cacheScope();
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9:_-]+/', '_', $name) ?: sha1($name);
    }

    private function snapshotRoot(): string
    {
        return rtrim((string) config('sitemap.snapshot_path', storage_path('app/sitemaps')), '/\\')
            .'/'.$this->tenant().'/'.$this->domainScope();
    }
}
