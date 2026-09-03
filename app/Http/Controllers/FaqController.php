<?php

namespace App\Http\Controllers;

use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FaqController extends Controller
{
    /** Ana sayfada gösterilecek soru sayısı. */
    private const MAX_ITEMS = 8;

    /** Panelde tanımlı SSS gruplarını getirir (GET /api/v2/faqs). */
    public static function getFrontendFaq($tenant, string $locale = 'de'): array
    {
        if (! $tenant) {
            return self::emptyPayload();
        }

        $locale = strtolower($locale);
        $mainTenant = OmrConfig::mainTenantId() ?: $tenant;
        $cacheKey = "faq_frontend_v1_{$tenant}_{$locale}";

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        $payload = (function () use ($tenant, $mainTenant, $locale) {
            try {
                $response = OmrCachedClient::get('faqs', 'faqs', [
                    'lang' => $locale,
                    'locale' => $locale,
                    'per_page' => 50,
                ], $mainTenant, [
                    'connect_timeout' => 2,
                    'timeout' => 5,
                    'success_ttl' => now()->addHours(6),
                    'stale_ttl' => now()->addDays(30),
                    'failure_ttl' => now()->addMinutes(10),
                    'rate_limit_ttl' => now()->addMinutes(30),
                    'cooldown_minutes' => 30,
                ]);

                if (! ($response['ok'] ?? false)) {
                    Log::warning('FAQ API non-200 response', [
                        'tenant' => $tenant,
                        'status' => $response['status'] ?? null,
                    ]);

                    return self::emptyPayload();
                }

                return self::normalize(self::extractRows($response), $locale);
            } catch (\Throwable $e) {
                Log::error('❌ FAQ API ERROR (Sessiz Fallback): '.$e->getMessage());

                return self::emptyPayload();
            }
        })();

        /*
         * /faqs uç noktası zaman zaman hiç yanıt vermiyor. Başarısız bir denemeyi
         * 6 saat boyunca önbelleğe almak, endpoint düzeldikten sonra da SSS'i
         * boş bırakırdı — bu yüzden boş sonuç sadece kısa süre saklanıyor.
         */
        Cache::put(
            $cacheKey,
            $payload,
            empty($payload['items']) ? now()->addMinutes(10) : now()->addHours(6),
        );

        return $payload;
    }

    private static function extractRows(array $response): array
    {
        $raw = data_get($response, 'json.data') ?? [];

        if (is_array($raw) && isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * API, konum bazlı birden fazla SSS grubu döndürüyor. Ana sayfa için
     * `order`/`id` sırasına göre ilk aktif grubu kullanıyoruz.
     */
    private static function normalize(array $groups, string $locale): array
    {
        $group = collect($groups)
            ->filter(fn ($item) => is_array($item))
            ->filter(fn ($item) => strtolower((string) ($item['status'] ?? 'active')) === 'active')
            ->filter(fn ($item) => ! empty($item['items']) && is_array($item['items']))
            ->sortBy([
                fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0),
                fn ($a, $b) => ($a['id'] ?? 0) <=> ($b['id'] ?? 0),
            ])
            ->first();

        if (! $group) {
            return self::emptyPayload();
        }

        $group = self::withTranslation($group, $locale);

        $items = collect($group['items'])
            ->filter(fn ($item) => is_array($item))
            ->map(fn ($item) => self::withTranslation($item, $locale))
            ->map(fn ($item) => [
                'id' => $item['id'] ?? null,
                'question' => trim((string) ($item['question'] ?? '')),
                'answer' => trim((string) ($item['answer'] ?? '')),
                'order' => $item['order'] ?? 0,
            ])
            ->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')
            ->sortBy('order')
            ->take(self::MAX_ITEMS)
            ->values()
            ->all();

        if ($items === []) {
            return self::emptyPayload();
        }

        return [
            'title' => trim((string) ($group['name'] ?? '')),
            'items' => $items,
            'is_demo' => false,
        ];
    }

    /** Çeviriler `translations` dizisinde geliyor; aktif dili üste bindir. */
    private static function withTranslation(array $row, string $locale): array
    {
        $translations = $row['translations'] ?? null;

        if (! is_array($translations)) {
            return $row;
        }

        foreach ($translations as $translation) {
            if (! is_array($translation)) {
                continue;
            }

            $code = strtolower((string) ($translation['language_code'] ?? $translation['locale'] ?? ''));

            if ($code !== '' && $code === $locale) {
                return array_merge($row, array_filter(
                    $translation,
                    static fn ($value) => $value !== null && $value !== '',
                ));
            }
        }

        return $row;
    }

    private static function emptyPayload(): array
    {
        return ['title' => '', 'items' => [], 'is_demo' => false];
    }
}
