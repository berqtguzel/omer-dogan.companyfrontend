<?php

namespace App\Http\Controllers;

use App\Support\MediaUrl;
use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /** Panelde onaylanan yorumları getirir (GET /api/v2/reviews). */
    public static function getFrontendReviews($tenant, string $locale = 'de', bool $mirror = true): array
    {
        if (! $tenant) {
            return self::emptyPayload();
        }

        $locale = strtolower($locale);
        $mainTenant = OmrConfig::mainTenantId() ?: $tenant;
        $cacheKey = "reviews_frontend_v1_{$tenant}_{$locale}";

        $cached = Cache::get($cacheKey);

        $payload = is_array($cached) ? $cached : (function () use ($tenant, $mainTenant, $locale) {
            try {
                $response = OmrCachedClient::get('reviews', 'reviews', [
                    'lang' => $locale,
                    'locale' => $locale,
                    'per_page' => 24,
                ], $mainTenant, [
                    'connect_timeout' => 1,
                    'timeout' => 3,
                    'success_ttl' => now()->addHours(6),
                    'stale_ttl' => now()->addDays(30),
                    'failure_ttl' => now()->addMinutes(10),
                    'rate_limit_ttl' => now()->addMinutes(30),
                    'cooldown_minutes' => 30,
                ]);

                if (! ($response['ok'] ?? false)) {
                    Log::warning('Review API non-200 response', [
                        'tenant' => $tenant,
                        'status' => $response['status'] ?? null,
                    ]);

                    return self::emptyPayload();
                }

                return self::normalize(self::extractRows($response), $locale);
            } catch (\Throwable $e) {
                Log::error('❌ Review API ERROR (Sessiz Fallback): '.$e->getMessage());

                return self::emptyPayload();
            }
        })();

        // Boş sonuç geçici bir API hatası da olabilir; kısa süre sakla.
        if (! is_array($cached)) {
            Cache::put(
                $cacheKey,
                $payload,
                empty($payload['items']) ? now()->addMinutes(10) : now()->addHours(6),
            );
        }

        return $mirror ? mirror_media($payload) : $payload;
    }

    /** Yanıt {data: [...]} ya da {data: {data: [...]}} şeklinde gelebiliyor. */
    private static function extractRows(array $response): array
    {
        $raw = data_get($response, 'json.data') ?? [];

        if (is_array($raw) && isset($raw['data']) && is_array($raw['data'])) {
            return $raw['data'];
        }

        return is_array($raw) ? $raw : [];
    }

    private static function normalize(array $rows, string $locale): array
    {
        $items = collect($rows)
            ->map(function ($row, $index) use ($locale) {
                if (! is_array($row)) {
                    return null;
                }

                $row = self::withTranslation($row, $locale);

                $comment = self::firstText($row, [
                    'comment', 'content', 'body', 'text', 'message', 'review', 'description',
                ]);

                if ($comment === '') {
                    return null;
                }

                $rating = self::firstValue($row, ['rating', 'stars', 'score', 'point', 'points']);
                $rating = is_numeric($rating) ? round((float) $rating, 1) : null;

                if ($rating !== null) {
                    $rating = max(0.0, min(5.0, $rating));
                }

                return [
                    'id' => $row['id'] ?? $index,
                    'name' => self::firstText($row, [
                        'author_name', 'customer_name', 'full_name', 'user_name', 'author', 'name',
                    ]),
                    'role' => self::firstText($row, [
                        'role', 'position', 'company', 'company_name', 'job_title', 'service_name', 'title',
                    ]),
                    'comment' => $comment,
                    'rating' => $rating,
                    'date' => self::firstText($row, [
                        'created_at', 'reviewed_at', 'published_at', 'date',
                    ]),
                    'avatar' => MediaUrl::resolve(self::firstValue($row, [
                        'avatar', 'avatar_url', 'image', 'image_url', 'photo', 'picture',
                    ])),
                    'source' => self::firstText($row, ['source', 'platform', 'provider']),
                    'order' => self::firstValue($row, ['order', 'sort_order', 'position']) ?? $index,
                ];
            })
            ->filter()
            ->sortBy('order')
            ->values()
            ->all();

        $rated = array_values(array_filter(
            array_column($items, 'rating'),
            static fn ($value) => $value !== null,
        ));

        return [
            'items' => $items,
            'summary' => [
                'count' => count($items),
                'average' => $rated === [] ? null : round(array_sum($rated) / count($rated), 1),
            ],
        ];
    }

    /**
     * Diğer uç noktalarda olduğu gibi çeviriler `translations` dizisinde
     * gelebiliyor; varsa aktif dilin alanlarını üste bindiriyoruz.
     */
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

    private static function firstValue(array $row, array $keys)
    {
        foreach ($keys as $key) {
            $value = $row[$key] ?? null;

            if (is_array($value)) {
                $value = $value['url'] ?? $value['path'] ?? $value['src'] ?? $value['id'] ?? null;
            }

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function firstText(array $row, array $keys): string
    {
        $value = self::firstValue($row, $keys);

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function emptyPayload(): array
    {
        return [
            'items' => [],
            'summary' => ['count' => 0, 'average' => null],
        ];
    }
}
