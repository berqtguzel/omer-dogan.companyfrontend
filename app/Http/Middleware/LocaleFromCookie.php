<?php

namespace App\Http\Middleware;

use App\Support\LocaleMapper;
use Closure;
use Illuminate\Http\Request;

class LocaleFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        // 1) URL'den ?lang= ile gelmişse onu kullan (en yüksek öncelik)
        $queryLocale = $request->query('lang');

        // 2) Cookie'den oku
        $cookieLocale = $request->cookie('locale');

        // 3) Session'dan oku (bozuk veri durumunda null döner)
        $sessionLocale = rescue(fn () => session('locale'), null);

        // 4) Varsayılan dil
        $defaultLocale = config('app.locale', 'de');

        // Öncelik sırası: query > cookie > session > default
        $locale = LocaleMapper::toWeb($queryLocale ?: $cookieLocale ?: $sessionLocale ?: $defaultLocale);

        // Desteklenen diller
        $available = LocaleMapper::WEB_LOCALES;

        if (! in_array($locale, $available)) {
            $locale = $defaultLocale;
        }

        // Session'a da kaydet (bozuk veri durumunda hata vermez)
        if ($locale !== $defaultLocale || $request->cookie('locale')) {
            rescue(fn () => session(['locale' => $locale]), null);
        }

        // Inertia'ya paylaş (props.locale) - HandleInertiaRequests middleware'inde zaten yapılıyor

        $response = $next($request);

        // Query parameter geldiyse veya cookie yoksa cookie'ye kaydet
        if ($queryLocale || (! $request->cookie('locale') && $locale !== $defaultLocale)) {
            $response->cookie('locale', $locale, 60 * 24 * 365, '/', null, false, false);
        }

        return $response;
    }
}
