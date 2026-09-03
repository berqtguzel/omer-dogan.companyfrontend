<?php

namespace App\Http\Middleware;

use App\Http\Controllers\LanguagesController;
use App\Support\LocaleMapper;
use App\Support\OmrConfig;
use Closure;

class ApplyLocale
{
    public function handle($request, Closure $next)
    {
        $locale = $request->route('locale')
            ?? session('locale', config('app.locale', 'de'));
        $locale = LocaleMapper::toWeb($locale);

        $tenantId = OmrConfig::tenantForSharedContent();

        // Network/cache failures must not make otherwise supported locale
        // routes disappear. A successful tenant language response may still
        // narrow this list below.
        $availableLocales = LocaleMapper::WEB_LOCALES;

        try {
            $data = LanguagesController::getLanguages($tenantId, 'de');

            if (! empty($data['languages']) && ! ($data['_fallback'] ?? false)) {
                $availableLocales = collect($data['languages'])
                    ->pluck('code')
                    ->map(fn ($l) => LocaleMapper::toWeb($l))
                    ->toArray();
            }

        } catch (\Throwable $e) {
            \Log::error('Locale error: '.$e->getMessage());
        }

        if (! in_array($locale, $availableLocales, true)) {
            abort(404);
        }

        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }
}
