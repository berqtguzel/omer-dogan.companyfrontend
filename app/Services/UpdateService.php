<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class UpdateService
{
    protected string $repoPath;

    protected string $lockPath;

    protected string $statusPath;

    protected string $versionPath;

    protected string $logPath;

    public function __construct()
    {
        $this->repoPath = base_path();
        $this->lockPath = storage_path('framework/update.lock');
        $this->statusPath = storage_path('framework/update-status.json');
        $this->versionPath = storage_path('framework/system-version.json');
        $this->logPath = storage_path('logs/update.log');
    }

    public function enabled(): bool
    {
        return (bool) config('update.enabled', false);
    }

  /** @return array<string, mixed> */
    public function status(): array
    {
        if (! File::exists($this->statusPath)) {
            return ['state' => 'idle'];
        }

        $decoded = json_decode((string) File::get($this->statusPath), true);

        return is_array($decoded) ? $decoded : ['state' => 'idle'];
    }

    public function isRunning(): bool
    {
        if (! File::exists($this->lockPath)) {
            return false;
        }

        $lock = json_decode((string) File::get($this->lockPath), true);
        $started = isset($lock['started_at']) ? strtotime((string) $lock['started_at']) : 0;

        if ($started && (time() - $started) > 1800) {
            $this->releaseLock();

            return false;
        }

        return true;
    }

  /** @return array<string, mixed>|null */
    public function checkLatestVersion(): ?array
    {
        $owner = (string) config('update.github_owner');
        $repo = (string) config('update.github_repo');

        if ($owner === '' || $repo === '') {
            return null;
        }

        $cacheKey = "update_github_latest_{$owner}_{$repo}";

        try {
            return Cache::remember($cacheKey, now()->addMinutes(5), function () {
                return $this->fetchLatestVersionFromGithub();
            });
        } catch (\Throwable $e) {
            Log::error('Update version check failed', ['error' => $e->getMessage()]);

            $cached = Cache::get($cacheKey);

            return is_array($cached) ? $cached : null;
        }
    }

  /** @return array<string, mixed>|null */
    protected function fetchLatestVersionFromGithub(): ?array
    {
        try {
            $latestRelease = $this->getLatestReleaseBySemver();
            $latestTag = $this->getLatestTagBySemver();

            $releaseVersion = $latestRelease ? ltrim((string) ($latestRelease['tag_name'] ?? ''), 'v') : '';
            $tagVersion = $latestTag ? ltrim((string) ($latestTag['tag_name'] ?? ''), 'v') : '';

            if ($latestTag && $tagVersion !== '' && version_compare($tagVersion, $releaseVersion, '>')) {
                return $this->formatVersionPayload($latestTag);
            }

            if ($latestRelease) {
                return [
                    'version' => $latestRelease['tag_name'] ?? 'unknown',
                    'commit_hash' => $latestRelease['target_commitish'] ?? null,
                    'changelog' => $latestRelease['body'] ?? '',
                    'published_at' => $latestRelease['published_at'] ?? null,
                ];
            }

            if ($latestTag) {
                return $this->formatVersionPayload($latestTag);
            }

            $latestCommit = $this->getLatestCommit();
            if ($latestCommit) {
                $sha = $latestCommit['sha'] ?? null;

                return [
                    'version' => 'main-'.substr((string) $sha, 0, 7),
                    'commit_hash' => $sha,
                    'changelog' => $latestCommit['commit']['message'] ?? 'Latest commit',
                    'published_at' => $latestCommit['commit']['author']['date'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            Log::error('Update version check failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

  /** @return array<string, mixed>|null */
    public function getCurrentVersion(): ?array
    {
        if (! File::exists($this->versionPath)) {
            return null;
        }

        $decoded = json_decode((string) File::get($this->versionPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function compareVersions(?string $currentVersion, ?string $latestVersion): bool
    {
        if (! $latestVersion) {
            return false;
        }

        if (! $currentVersion) {
            return true;
        }

        $current = ltrim($currentVersion, 'v');
        $latest = ltrim($latestVersion, 'v');

        if (preg_match('/^\d+\.\d+\.\d+$/', $current) && preg_match('/^\d+\.\d+\.\d+$/', $latest)) {
            return version_compare($latest, $current, '>');
        }

        return $currentVersion !== $latestVersion;
    }

  /** @return array{success:bool, logs:list<string>} */
    public function performUpdate(string $version = 'latest', ?string $changelog = null): array
    {
        @set_time_limit((int) config('update.timeout', 900));
        @ini_set('max_execution_time', (string) config('update.timeout', 900));

        $logs = [];

        if (! $this->enabled()) {
            return ['success' => false, 'logs' => ['UPDATE_ENABLED=false']];
        }

        if ($this->isRunning()) {
            return ['success' => false, 'logs' => ['Update already running']];
        }

        $this->acquireLock();
        $this->writeStatus('running', 'Update başladı');

        try {
            try {
                Artisan::call('config:clear');
            } catch (\Throwable) {
            }

            $logs[] = 'Update started at '.now()->toDateTimeString();
            $this->ensurePaths($logs);
            $this->clearBootstrapPackageCache($logs);

            if ($this->isGitRepository()) {
                $sync = $this->gitSyncAndCheckout($version);
            } elseif (config('update.archive_fallback', true)) {
                $logs[] = 'Git yok — GitHub archive modu kullanılıyor.';
                $sync = $this->archiveSyncFromGithub($version);
            } else {
                throw new \RuntimeException('Sunucuda git repository bulunamadı. UPDATE_ARCHIVE_FALLBACK=true yapın veya git init kurun.');
            }

            if (! ($sync['success'] ?? false)) {
                throw new \RuntimeException($sync['error'] ?? 'Kaynak senkronizasyonu başarısız');
            }
            $logs = array_merge($logs, $sync['logs'] ?? []);
            $this->clearBootstrapPackageCache($logs);

            try {
                Artisan::call('config:clear');
                $logs[] = '$ php artisan config:clear (post-sync)';
            } catch (\Throwable) {
            }

            if (config('update.run_composer', true)) {
                $composer = $this->composerInstall();
                if (! ($composer['success'] ?? false)) {
                    throw new \RuntimeException($composer['error'] ?? 'Composer failed');
                }
                $logs = array_merge($logs, $composer['logs'] ?? []);
                $this->clearBootstrapPackageCache($logs);
                $logs = array_merge($logs, $this->packageDiscover());
            }

            if (config('update.run_npm', false)) {
                $npm = $this->npmInstall();
                if (! ($npm['success'] ?? false)) {
                    throw new \RuntimeException($npm['error'] ?? 'npm install failed');
                }
                $logs = array_merge($logs, $npm['logs'] ?? []);
            } else {
                $logs[] = 'npm install skipped (UPDATE_RUN_NPM=false, using pre-built assets from git).';
            }

            if (config('update.run_build', false)) {
                $build = $this->npmBuild();
                if (! ($build['success'] ?? false)) {
                    throw new \RuntimeException($build['error'] ?? 'npm build failed');
                }
                $logs = array_merge($logs, $build['logs'] ?? []);
            } else {
                $logs[] = 'npm build skipped (UPDATE_RUN_BUILD=false, using pre-built assets from git).';
            }

            $logs = array_merge($logs, $this->verifyPrebuiltAssets());

            $logs = array_merge($logs, $this->rebuildCaches());
            $logs = array_merge($logs, $this->storageLink());

            if (config('update.run_media_sync', false)) {
                $logs[] = 'Running media:sync...';
                Artisan::call('media:sync', ['--regenerate' => true, '--prune' => true]);
                $logs[] = Artisan::output();
            }

            $latest = $this->checkLatestVersion();
            $savedVersion = $version === 'latest'
                ? ($latest['version'] ?? 'latest')
                : $version;

            $this->saveVersion($savedVersion, $changelog, $latest['commit_hash'] ?? null);

            $logs[] = 'Update completed at '.now()->toDateTimeString();
            $this->writeStatus('success', 'Update tamamlandı', ['logs' => $logs]);

            return ['success' => true, 'logs' => $logs];
        } catch (\Throwable $e) {
            $logs[] = 'ERROR: '.$e->getMessage();
            Log::error('System update failed', ['error' => $e->getMessage()]);
            $this->writeStatus('failed', $e->getMessage(), ['logs' => $logs]);

            return ['success' => false, 'logs' => $logs];
        } finally {
            $this->releaseLock();
        }
    }

    public function dispatchBackground(string $version = 'latest'): bool
    {
        if ($this->isRunning()) {
            return false;
        }

        $php = escapeshellarg($this->phpBinary());
        $artisan = escapeshellarg(base_path('artisan'));
        $ver = escapeshellarg($version);
        $log = escapeshellarg($this->logPath);

        if (PHP_OS_FAMILY === 'Windows') {
            $command = "start /B {$php} {$artisan} update:run {$ver} >> {$log} 2>&1";
        } else {
            $command = "{$php} {$artisan} update:run {$ver} >> {$log} 2>&1 &";
        }

        exec($command);

        return true;
    }

  /** @return array{success:bool, logs:list<string>, error?:string} */
    protected function gitSyncAndCheckout(string $version): array
    {
        $logs = [];
        $branch = (string) config('update.github_branch', 'main');
        $root = $this->repoPath;

        $run = function (string $cmd) use (&$logs, $root): bool {
            $out = [];
            $rc = 0;
            exec('cd '.escapeshellarg($root).' && '.$cmd.' 2>&1', $out, $rc);
            $logs[] = '$ '.$this->maskSecrets($cmd);
            if ($out !== []) {
                $logs = array_merge($logs, array_map(fn ($line) => $this->maskSecrets((string) $line), $out));
            }

            return $rc === 0;
        };

        $run('git config --local safe.directory '.escapeshellarg($root));
        $this->ensureGitRemote($run, $logs);

        if (! $run('/usr/bin/git fetch --all --prune')) {
            $detail = end($logs) ?: 'no output';

            return ['success' => false, 'error' => 'git fetch failed: '.$detail, 'logs' => $logs];
        }

        $run('git fetch origin \'+refs/tags/*:refs/tags/*\'');

        if (! $run('git reset --hard origin/'.$branch)) {
            $branch = 'main';
            if (! $run('git reset --hard origin/'.$branch)) {
                $branch = 'master';
                if (! $run('git reset --hard origin/'.$branch)) {
                    return ['success' => false, 'error' => 'git reset failed', 'logs' => $logs];
                }
            }
            $logs[] = "Branch fallback: {$branch}";
        }

        if (! $run('git clean -fd --exclude=.env* --exclude=storage --exclude=vendor --exclude=node_modules')) {
            return ['success' => false, 'error' => 'git clean failed', 'logs' => $logs];
        }

        $ver = trim($version);
        if ($ver !== '' && $ver !== 'latest') {
            $tagCheck = [];
            $rc = 0;
            exec('cd '.escapeshellarg($root).' && git rev-parse -q --verify refs/tags/'.escapeshellarg($ver).' 2>&1', $tagCheck, $rc);

            if ($rc === 0) {
                $logs[] = "Checking out tag: {$ver}";
                if (! $run('git checkout -f '.$ver)) {
                    return ['success' => false, 'error' => 'git checkout tag failed', 'logs' => $logs];
                }
            } elseif (preg_match('/^[0-9a-f]{7,40}$/i', $ver)) {
                $logs[] = "Checking out commit: {$ver}";
                if (! $run('git checkout -f '.$ver)) {
                    return ['success' => false, 'error' => 'git checkout commit failed', 'logs' => $logs];
                }
            }
        }

        return ['success' => true, 'logs' => $logs];
    }

  /** @return array{success:bool, logs:list<string>, error?:string} */
    protected function archiveSyncFromGithub(string $version): array
    {
        $logs = [];
        $owner = (string) config('update.github_owner');
        $repo = (string) config('update.github_repo');
        $branch = (string) config('update.github_branch', 'main');
        $ref = ($version === '' || $version === 'latest') ? $branch : $version;

        if ($owner === '' || $repo === '') {
            return ['success' => false, 'error' => 'GITHUB_REPO_OWNER / GITHUB_REPO_NAME eksik', 'logs' => $logs];
        }

        if (! class_exists(\ZipArchive::class)) {
            return ['success' => false, 'error' => 'PHP ZipArchive extension gerekli', 'logs' => $logs];
        }

        $url = "https://api.github.com/repos/{$owner}/{$repo}/zipball/{$ref}";
        $logs[] = 'Downloading GitHub archive: '.$url;

        $request = Http::connectTimeout(5)
            ->timeout(20)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'tenant-site-update',
            ]);

        $token = (string) config('update.github_token');
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $response = $request->get($url);

        if (! $response->successful()) {
            $message = 'GitHub archive indirilemedi (HTTP '.$response->status().'). Private repo için GITHUB_TOKEN gerekir.';

            return ['success' => false, 'error' => $message, 'logs' => $logs];
        }

        $workDir = storage_path('app/update');
        File::ensureDirectoryExists($workDir);

        $zipPath = $workDir.'/archive-'.time().'.zip';
        File::put($zipPath, $response->body());
        $logs[] = 'Archive saved: '.$zipPath.' ('.number_format(strlen($response->body())).' bytes)';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);

            return ['success' => false, 'error' => 'Zip dosyası açılamadı', 'logs' => $logs];
        }

        $extractPath = $workDir.'/extract-'.time();
        File::ensureDirectoryExists($extractPath);

        if (! $zip->extractTo($extractPath)) {
            $zip->close();
            @unlink($zipPath);
            File::deleteDirectory($extractPath);

            return ['success' => false, 'error' => 'Zip çıkarılamadı', 'logs' => $logs];
        }

        $zip->close();
        @unlink($zipPath);

        $dirs = glob($extractPath.'/*', GLOB_ONLYDIR) ?: [];
        if ($dirs === []) {
            File::deleteDirectory($extractPath);

            return ['success' => false, 'error' => 'Archive içinde kaynak klasör bulunamadı', 'logs' => $logs];
        }

        $sourceRoot = $dirs[0];
        $this->copyDeployFiles($sourceRoot, $this->repoPath, $logs);
        File::deleteDirectory($extractPath);

        $logs[] = 'GitHub archive deploy tamamlandı (ref: '.$ref.')';

        return ['success' => true, 'logs' => $logs];
    }

  /** @param  list<string>  $logs */
    protected function copyDeployFiles(string $source, string $destination, array &$logs): void
    {
        $excludeDirs = ['storage', 'vendor', 'node_modules', '.git'];
        $excludeFiles = ['.env', '.env.backup', '.env.production'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $copied = 0;

        foreach ($iterator as $item) {
            if (! $item instanceof \SplFileInfo) {
                continue;
            }

            $relative = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $source)) + 1);
            $topSegment = explode('/', $relative)[0] ?? '';

            if (in_array($topSegment, $excludeDirs, true)) {
                continue;
            }

            if (in_array($item->getBasename(), $excludeFiles, true)) {
                continue;
            }

            $target = $destination.'/'.$relative;

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    @mkdir($target, 0775, true);
                }

                continue;
            }

            $targetDir = dirname($target);
            if (! is_dir($targetDir)) {
                @mkdir($targetDir, 0775, true);
            }

            if (@copy($item->getPathname(), $target)) {
                $copied++;
            }
        }

        $logs[] = "Deployed {$copied} files from archive (storage/.env/vendor korundu)";
    }

  /** @return array{success:bool, logs:list<string>, error?:string} */
    protected function composerInstall(): array
    {
        $composerHome = storage_path('app/composer');
        if (! is_dir($composerHome)) {
            @mkdir($composerHome, 0775, true);
        }

        $php = $this->resolvePhpCli();
        $composer = $this->resolveComposerBin();
        $args = 'install --no-dev --prefer-dist --no-interaction --optimize-autoloader';
        $command = $this->buildComposerShellCommand($args);

        $output = [];
        $rc = 0;
        exec($command['shell'], $output, $rc);

        return [
            'success' => $rc === 0,
            'logs' => array_merge([$command['log']], $output),
            'error' => $rc === 0 ? null : implode("\n", $output),
        ];
    }

  /** @return array{shell: string, log: string} */
    protected function buildComposerShellCommand(string $arguments): array
    {
        $php = $this->resolvePhpCli();
        $composer = $this->resolveComposerBin();
        $composerHome = storage_path('app/composer');

        $env = 'PATH=/usr/bin:/bin:/usr/local/bin'
            .' HOME='.escapeshellarg($composerHome)
            .' COMPOSER_HOME='.escapeshellarg($composerHome)
            .' COMPOSER_ALLOW_SUPERUSER=1'
            .' COMPOSER_PHP='.escapeshellarg($php);

        $isPhar = str_ends_with(strtolower($composer), '.phar')
            || str_ends_with(strtolower($composer), '.php');

        if ($isPhar) {
            $invoke = escapeshellarg($php).' '.escapeshellarg($composer).' '.$arguments;
            $log = '$ '.$php.' '.$composer.' '.$arguments;
        } else {
            // All-Inkl: /usr/bin/composer is a shell wrapper, not a PHP file.
            $invoke = escapeshellarg($composer).' '.$arguments;
            $log = '$ COMPOSER_PHP='.$php.' '.$composer.' '.$arguments;
        }

        return [
            'shell' => 'cd '.escapeshellarg($this->repoPath).' && '.$env.' '.$invoke.' 2>&1',
            'log' => $log,
        ];
    }

    protected function maskSecrets(string $value): string
    {
        $token = (string) config('update.github_token');
        if ($token !== '') {
            $value = str_replace($token, '***GITHUB_TOKEN***', $value);
        }

        return (string) preg_replace(
            '/https:\/\/(?:[^@\s]+)@github\.com/',
            'https://***@github.com',
            $value
        );
    }

  /** @return array{success:bool, logs:list<string>, error?:string} */
    protected function npmInstall(): array
    {
        $npm = escapeshellcmd($this->npmBinary());
        $full = "{$npm} install --legacy-peer-deps 2>&1";
        $out = [];
        $rc = 0;
        exec('cd '.escapeshellarg($this->repoPath).' && '.$full, $out, $rc);

        return [
            'success' => $rc === 0,
            'logs' => array_merge(['$ '.$full], $out),
            'error' => $rc === 0 ? null : implode("\n", $out),
        ];
    }

  /** @return array{success:bool, logs:list<string>, error?:string} */
    protected function npmBuild(): array
    {
        $npm = escapeshellcmd($this->npmBinary());
        $full = "{$npm} run build 2>&1";
        $out = [];
        $rc = 0;
        exec('cd '.escapeshellarg($this->repoPath).' && '.$full, $out, $rc);

        return [
            'success' => $rc === 0,
            'logs' => array_merge(['$ '.$full], $out),
            'error' => $rc === 0 ? null : implode("\n", $out),
        ];
    }

  /** @return list<string> */
    protected function rebuildCaches(): array
    {
        $logs = ['Clearing caches...'];
        $this->clearBootstrapPackageCache($logs);

        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $command) {
            try {
                Artisan::call($command);
                $logs[] = "$ {$command}";
                $logs[] = trim(Artisan::output());
            } catch (\Throwable $e) {
                $logs[] = "{$command} warning: ".$e->getMessage();
            }
        }

        return $logs;
    }

  /** @return list<string> */
    protected function packageDiscover(): array
    {
        $php = escapeshellarg($this->resolvePhpCli());
        $artisan = escapeshellarg(base_path('artisan'));
        $shell = 'cd '.escapeshellarg($this->repoPath).' && '.$php.' '.$artisan.' package:discover --ansi 2>&1';

        $output = [];
        $rc = 0;
        exec($shell, $output, $rc);

        $logs = array_merge(['$ php artisan package:discover --ansi'], $output);

        if ($rc !== 0) {
            throw new \RuntimeException('package:discover failed: '.implode("\n", $output));
        }

        return $logs;
    }

  /** @return list<string> */
    protected function verifyPrebuiltAssets(): array
    {
        $logs = [];
        $manifest = public_path('build/manifest.json');
        $ssr = base_path('bootstrap/ssr/ssr.js');

        if (! is_file($manifest)) {
            $logs[] = 'WARNING: public/build/manifest.json missing — run npm run build locally or enable GitHub Actions build.';

            return $logs;
        }

        $logs[] = 'Pre-built assets OK: public/build/manifest.json';

        if (config('inertia.ssr.enabled') && ! is_file($ssr)) {
            $logs[] = 'WARNING: bootstrap/ssr/ssr.js missing — SSR may fail. Run npm run build or disable SSR.';
        } elseif (is_file($ssr)) {
            $logs[] = 'SSR bundle OK: bootstrap/ssr/ssr.js';
        }

        return $logs;
    }

  /** @return list<string> */
    protected function storageLink(): array
    {
        try {
            Artisan::call('storage:link');

            return ['$ php artisan storage:link', trim(Artisan::output())];
        } catch (\Throwable $e) {
            return ['storage:link warning: '.$e->getMessage()];
        }
    }

    protected function saveVersion(string $version, ?string $changelog, ?string $commitHash): void
    {
        File::put($this->versionPath, json_encode([
            'version' => $version,
            'changelog' => $changelog,
            'commit_hash' => $commitHash,
            'installed_at' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function isGitRepository(): bool
    {
        return is_dir($this->repoPath.'/.git');
    }

  /** @param  list<string>  $logs */
    protected function ensurePaths(array &$logs): void
    {
        foreach ([
            storage_path('framework'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            base_path('bootstrap/cache'),
            storage_path('app/composer'),
        ] as $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
                $logs[] = "Created dir: {$path}";
            }
        }
    }

  /** @param  list<string>  $logs */
    protected function clearBootstrapPackageCache(array &$logs): void
    {
        foreach (['packages.php', 'services.php', 'routes-v7.php'] as $file) {
            $path = base_path('bootstrap/cache/'.$file);

            if (is_file($path) && @unlink($path)) {
                $logs[] = "Removed stale bootstrap cache: {$file}";
            }
        }
    }

  /** @param  array<string, mixed>  $tag */
    protected function formatVersionPayload(array $tag): array
    {
        return [
            'version' => $tag['tag_name'] ?? 'unknown',
            'commit_hash' => $tag['object_sha'] ?? null,
            'changelog' => $tag['body'] ?? '',
            'published_at' => $tag['published_at'] ?? null,
        ];
    }

  /** @return array<string, mixed>|null */
    protected function getLatestReleaseBySemver(): ?array
    {
        $releases = $this->githubGet('/releases?per_page=100');
        if ($releases === []) {
            return null;
        }

        $candidates = array_values(array_filter($releases, function ($release) {
            if (! is_array($release) || ! empty($release['draft']) || ! empty($release['prerelease'])) {
                return false;
            }

            return preg_match('/^v?\d+\.\d+\.\d+$/', (string) ($release['tag_name'] ?? '')) === 1;
        }));

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            return version_compare(
                ltrim((string) ($b['tag_name'] ?? ''), 'v'),
                ltrim((string) ($a['tag_name'] ?? ''), 'v')
            );
        });

        return $candidates[0] ?? null;
    }

  /** @return array<string, mixed>|null */
    protected function getLatestTagBySemver(): ?array
    {
        $refs = $this->githubGet('/git/refs/tags?per_page=100');
        $candidates = [];

        foreach ($refs as $ref) {
            $refName = (string) ($ref['ref'] ?? '');
            if (! str_starts_with($refName, 'refs/tags/')) {
                continue;
            }

            $tagName = substr($refName, 10);
            if (preg_match('/^v?\d+\.\d+\.\d+$/', $tagName) !== 1) {
                continue;
            }

            $candidates[] = [
                'tag_name' => $tagName,
                'object_sha' => $ref['object']['sha'] ?? null,
                'body' => '',
                'published_at' => null,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, function ($a, $b) {
            return version_compare(
                ltrim((string) ($b['tag_name'] ?? ''), 'v'),
                ltrim((string) ($a['tag_name'] ?? ''), 'v')
            );
        });

        return $candidates[0] ?? null;
    }

  /** @return array<string, mixed>|null */
    protected function getLatestCommit(): ?array
    {
        $branch = (string) config('update.github_branch', 'main');
        $commits = $this->githubGet('/commits', ['sha' => $branch, 'per_page' => 1]);

        return $commits[0] ?? null;
    }

  /** @return list<array<string, mixed>> */
    protected function githubGet(string $path, array $query = []): array
    {
        $owner = (string) config('update.github_owner');
        $repo = (string) config('update.github_repo');
        $token = (string) config('update.github_token');

        if ($owner === '' || $repo === '') {
            return [];
        }

        $cacheKey = 'update_github_api_'.md5($owner.'/'.$repo.$path.json_encode($query));

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($owner, $repo, $path, $query, $token) {
            $request = Http::connectTimeout(5)
                ->timeout(20)
                ->withHeaders(['User-Agent' => 'Tenant-Site-Update'])
                ->acceptJson();

            if ($token !== '') {
                $request = $request->withToken($token);
            }

            if (app()->environment(['local', 'development'])) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get("https://api.github.com/repos/{$owner}/{$repo}{$path}", $query);

            if ($response->status() === 429) {
                Log::warning('GitHub API rate limit hit', ['path' => $path]);
                $stale = Cache::get($cacheKey.'_stale');

                return is_array($stale) ? $stale : [];
            }

            if (! $response->successful()) {
                Log::warning('GitHub API request failed', [
                    'path' => $path,
                    'status' => $response->status(),
                ]);

                return [];
            }

            $json = $response->json();
            $data = is_array($json) ? $json : [];

            Cache::put($cacheKey.'_stale', $data, now()->addDay());

            return $data;
        });
    }

    protected function phpBinary(): string
    {
        return $this->resolvePhpCli();
    }

    protected function resolvePhpCli(): string
    {
        return $this->resolveBinaryPath(
            (string) config('update.php_cli', '/usr/bin/php82'),
            ['/usr/bin/php82', '/usr/bin/php83', '/usr/bin/php8.2', '/usr/bin/php8.3']
        );
    }

    protected function resolveComposerBin(): string
    {
        return $this->resolveBinaryPath(
            (string) config('update.composer_bin', '/usr/bin/composer'),
            ['/usr/bin/composer', '/usr/local/bin/composer', base_path('composer.phar')]
        );
    }

    /** @param  list<string>  $fallbacks */
    protected function resolveBinaryPath(string $configured, array $fallbacks): string
    {
        $configured = trim($configured);

        if ($configured !== '' && str_starts_with($configured, '/')) {
            return $configured;
        }

        foreach ($fallbacks as $path) {
            if (@is_file($path)) {
                return $path;
            }
        }

        return $fallbacks[0] ?? $configured;
    }

  /** @param  callable(string): bool  $run  */
  /** @param  list<string>  $logs  */
    protected function ensureGitRemote(callable $run, array &$logs): void
    {
        $owner = (string) config('update.github_owner');
        $repo = (string) config('update.github_repo');
        $token = (string) config('update.github_token');

        if ($owner === '' || $repo === '') {
            return;
        }

        $remoteUrl = $token !== ''
            ? 'https://'.rawurlencode($token).'@github.com/'.$owner.'/'.$repo.'.git'
            : 'https://github.com/'.$owner.'/'.$repo.'.git';

        $check = [];
        $rc = 0;
        exec('cd '.escapeshellarg($this->repoPath).' && git remote get-url origin 2>&1', $check, $rc);

        if ($rc !== 0) {
            $run('git remote add origin '.escapeshellarg($remoteUrl));
            $logs[] = 'git remote origin eklendi';

            return;
        }

        $run('git remote set-url origin '.escapeshellarg($remoteUrl));

        if ($token !== '') {
            $logs[] = 'git remote origin token ile güncellendi (private repo)';
        }
    }

    protected function npmBinary(): string
    {
        $out = [];
        @exec('which npm 2>&1', $out);
        if (! empty($out[0]) && file_exists(trim($out[0]))) {
            return trim($out[0]);
        }

        foreach (['/usr/bin/npm', '/usr/local/bin/npm'] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return 'npm';
    }

  /** @param  array<string, mixed>|null  $extra */
    protected function writeStatus(string $state, string $message, ?array $extra = null): void
    {
        File::put($this->statusPath, json_encode(array_merge([
            'state' => $state,
            'message' => $message,
            'updated_at' => now()->toIso8601String(),
            'site' => config('app.url'),
        ], $extra ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function acquireLock(): void
    {
        File::put($this->lockPath, json_encode([
            'pid' => getmypid(),
            'started_at' => now()->toIso8601String(),
        ]));
    }

    protected function releaseLock(): void
    {
        if (File::exists($this->lockPath)) {
            File::delete($this->lockPath);
        }
    }
}
