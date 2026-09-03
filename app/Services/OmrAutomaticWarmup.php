<?php

namespace App\Services;

use App\Http\Controllers\StaticPageController;
use App\Support\LocaleMapper;
use App\Support\Omr\OmrCatalog;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class OmrAutomaticWarmup
{
    public function scheduleAfterResponse(): void
    {
        if (! config('omr_warmup.enabled', true) || $this->isComplete()) {
            return;
        }

        $scheduledKey = $this->key('scheduled');

        if (! Cache::add($scheduledKey, true, now()->addMinutes(15))) {
            return;
        }

        app()->terminating(function () use ($scheduledKey): void {
            try {
                $this->run();
            } catch (\Throwable $e) {
                Log::error('Automatic OMR warmup failed', ['error' => $e->getMessage()]);
            } finally {
                Cache::forget($scheduledKey);
            }
        });
    }

    public function run(): bool
    {
        if ($this->isComplete()) {
            return true;
        }

        $lock = Cache::lock($this->key('lock'), 1800);

        if (! $lock->get()) {
            return false;
        }

        try {
            $locale = strtolower((string) config('omr_warmup.source_locale', 'de'));
            $perPage = (int) config('omr_warmup.catalog.per_page', 100);
            $sleep = (int) config('omr_warmup.catalog.sleep_ms', 2500);

            $pagesExit = Artisan::call('omr:warm-pages', [
                '--locale' => $locale,
                '--all-locales' => true,
                '--force' => true,
            ]);

            $catalogExit = Artisan::call('omr:warm-catalog', [
                '--locale' => $locale,
                '--max-pages' => (int) config('omr_warmup.catalog.max_pages', 120),
                '--per-page' => $perPage,
                '--sleep' => $sleep,
                '--limit-categories' => (int) config('omr_warmup.catalog.limit_categories', 1),
                '--force' => true,
            ]);

            if (config('omr_warmup.media', true)) {
                Artisan::call('media:sync', ['--locale' => $locale]);
                Artisan::call('media:sync', ['--locale' => $locale, '--cards' => true]);
            }

            $ready = $pagesExit === 0
                && $catalogExit === 0
                && StaticPageController::hasFreshPages($locale)
                && OmrCatalog::hasFreshServices(
                    OmrConfig::tenantForSharedContent(),
                    $locale,
                    [
                        'parent_id' => 'null',
                        '_max_pages' => 1,
                        '_per_page' => $perPage,
                        '_sleep_ms' => $sleep,
                    ],
                );

            if ($ready) {
                Cache::forever($this->key('complete'), [
                    'completed_at' => now()->toIso8601String(),
                    'locales' => LocaleMapper::WEB_LOCALES,
                ]);
            }

            Log::info('Automatic OMR warmup finished', [
                'ready' => $ready,
                'pages_exit' => $pagesExit,
                'catalog_exit' => $catalogExit,
            ]);

            return $ready;
        } finally {
            $lock->release();
        }
    }

    private function isComplete(): bool
    {
        return Cache::has($this->key('complete'));
    }

    public function invalidate(): void
    {
        Cache::forget($this->key('complete'));
        Cache::forget($this->key('scheduled'));
    }

    private function key(string $suffix): string
    {
        $tenant = OmrConfig::tenantForSharedContent() ?: OmrConfig::tenantId() ?: 'missing';
        $version = (string) config('omr_warmup.version', '1');

        return "omr_auto_warm_{$suffix}_{$tenant}_v{$version}";
    }
}
