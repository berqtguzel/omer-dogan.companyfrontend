<?php

namespace App\Http\Controllers;

use App\Services\MediaMirrorService;
use App\Services\SeoFilesService;
use App\Services\TenantCanonicalUrlResolver;
use App\Support\OmrConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoFilesController extends Controller
{
    public function __construct(
        private readonly SeoFilesService $seoFiles,
        private readonly TenantCanonicalUrlResolver $canonicalUrls,
        private readonly MediaMirrorService $mediaMirror
    ) {}

    public function favicon(): Response|RedirectResponse
    {
        $tenant = OmrConfig::tenantId();
        $locale = OmrConfig::defaultLocale();
        $cacheKey = 'site_favicon_v1_'.$tenant.'_'.$this->canonicalUrls->cacheScope($tenant);

        $favicon = Cache::remember($cacheKey, now()->addDay(), function () use ($tenant, $locale) {
            $settings = SettingsController::getFrontendSettings($tenant, $locale, false);
            $branding = is_array($settings['branding'] ?? null) ? $settings['branding'] : [];
            $url = $branding['site_favicon_url']
                ?? $settings['site_favicon_url']
                ?? $branding['favicon_url']
                ?? $branding['site_favicon']
                ?? $settings['site_favicon']
                ?? $branding['favicon']
                ?? null;

            if (! is_string($url) || trim($url) === '') {
                return null;
            }

            return $this->mediaMirror->mirrorIfUrl(trim($url)) ?: trim($url);
        });

        if (! is_string($favicon) || $favicon === '') {
            return response('', 204, ['Cache-Control' => 'no-store']);
        }

        return redirect()->to($favicon, 302, [
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    public function humans(): Response
    {
        return $this->serve('humans');
    }

    public function robots(): Response
    {
        $baseUrl = $this->canonicalUrls->baseUrl();

        if (! $baseUrl) {
            return response("User-agent: *\nAllow: /\n", 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        return response(
            "User-agent: *\nAllow: /\n\nSitemap: {$baseUrl}/sitemap.xml\n",
            200,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]
        );
    }

    public function llms(): Response
    {
        return $this->serve('llms');
    }

    public function manifest(): Response
    {
        return $this->serve('manifest');
    }

    public function schema(): Response
    {
        return $this->serve('schema');
    }

    public function security(): Response
    {
        return $this->serve('security');
    }

    public function identity(): Response
    {
        return $this->serve('identity');
    }

    public function brand(): Response
    {
        return $this->serve('brand');
    }

    public function knowledge(): Response
    {
        return $this->serve('knowledge');
    }

    public function ai(): Response
    {
        return $this->serve('ai');
    }

    public function aiBrandMarkdown(): Response
    {
        return $this->serve('ai_brand_md');
    }

    public function aiKnowledgeMarkdown(): Response
    {
        return $this->serve('ai_knowledge_md');
    }

    public function aiAboutMarkdown(): Response
    {
        return $this->serve('ai_about_md');
    }

    public function sync(): JsonResponse
    {
        return response()->json($this->seoFiles->syncAll(OmrConfig::tenantId()));
    }

    private function serve(string $type): Response
    {
        $file = $this->seoFiles->sync($type, OmrConfig::tenantId());

        abort_if($file === null, 404);

        return response($file['content'], 200)
            ->header('Content-Type', $file['mime_type'])
            ->header('Cache-Control', 'public, max-age=300, stale-while-revalidate=3600')
            ->header('X-SEO-File-Type', $file['type'])
            ->header('X-SEO-File-Hash', $file['hash']);
    }
}
