<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LegacyUrlRedirectController extends Controller
{
    public function defaultService(Request $request, string $slug): RedirectResponse
    {
        return $this->serviceRedirect($request, 'de', $slug);
    }

    public function localizedService(Request $request, string $locale, string $slug): RedirectResponse
    {
        return $this->serviceRedirect($request, $locale, $slug);
    }

    public function localeChain(
        Request $request,
        string $locale,
        string $duplicateLocale,
        ?string $path = null
    ): RedirectResponse {
        return $this->permanentRedirect(
            $request,
            $this->localizedPath($locale, $path)
        );
    }

    public function publicPath(Request $request, ?string $path = null): RedirectResponse
    {
        $segments = $this->segments($path);
        $locale = null;

        while ($segments !== [] && LocaleMapper::isSupportedWeb($segments[0])) {
            $locale ??= LocaleMapper::toWeb(array_shift($segments));

            if ($locale !== null && $segments !== [] && LocaleMapper::isSupportedWeb($segments[0])) {
                array_shift($segments);
            }
        }

        $target = '/'.implode('/', array_filter([$locale, ...$segments]));

        if ($target === '/' || ($locale !== null && $segments === [])) {
            $target = $locale ? "/{$locale}/" : '/';
        }

        return $this->permanentRedirect($request, $target);
    }

    private function localizedPath(string $locale, ?string $path): string
    {
        $locale = LocaleMapper::toWeb($locale);
        $segments = $this->segments($path);

        while ($segments !== [] && LocaleMapper::isSupportedWeb($segments[0])) {
            array_shift($segments);
        }

        return $segments === []
            ? "/{$locale}/"
            : "/{$locale}/".implode('/', $segments);
    }

    private function serviceRedirect(Request $request, string $locale, string $slug): RedirectResponse
    {
        $locale = LocaleMapper::toWeb($locale);
        $slug = trim(rawurldecode($slug), '/');

        abort_if($slug === '' || str_contains($slug, '/'), 404);

        return $this->permanentRedirect($request, "/{$locale}/{$slug}");
    }

    private function segments(?string $path): array
    {
        return array_values(array_filter(
            explode('/', trim((string) $path, '/')),
            fn ($segment) => $segment !== ''
        ));
    }

    private function permanentRedirect(Request $request, string $target): RedirectResponse
    {
        $query = $request->getQueryString();

        return redirect()->to($target.($query ? '?'.$query : ''), 301);
    }
}
