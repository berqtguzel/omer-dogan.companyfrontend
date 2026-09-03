<?php

namespace App\Services;

use App\Support\LocaleMapper;
use Illuminate\Support\Facades\Log;

final class ServiceTranslationResolver
{
    private const LOCALIZED_FIELDS = [
        'name', 'content', 'short_description', 'meta_title', 'meta_description', 'meta_keywords',
    ];

    public function hasUsableTranslation(array $service, string $locale): bool
    {
        $webLocale = LocaleMapper::toWeb($locale);
        $translation = $this->findTranslation($service, $webLocale);

        if ($translation === null
            || ! $this->hasText($translation['name'] ?? null)
            || ! $this->hasText($translation['content'] ?? null, true)) {
            return false;
        }

        if ($webLocale === 'de') {
            return true;
        }

        $german = $this->findTranslation($service, 'de');

        return $german === null
            || $this->normalizedContent($translation['content'] ?? null)
                !== $this->normalizedContent($german['content'] ?? null);
    }

    public function resolve(array $service, string $locale, string $tenant): array
    {
        $webLocale = LocaleMapper::toWeb($locale);
        $translation = $this->findTranslation($service, $webLocale);

        if ($translation !== null && $this->hasUsableTranslation($service, $webLocale)) {
            Log::debug('Service API translation used', $this->context($service, $tenant, $webLocale));
            $resolved = $this->applyTranslation($service, $translation, $webLocale);
            $localizedFaq = $this->localizedFaq($service, $webLocale);

            if ($localizedFaq !== null) {
                $resolved['faq'] = $localizedFaq;

                return $this->result($resolved, 'api', true);
            }

            $resolved['faq'] = $this->sourceFaq($service);

            return $this->result($resolved, 'api+fallback_faq', false, 'faq_translation_unavailable');
        }

        $source = $this->germanSource($service);

        if ($source === null) {
            Log::warning('German service translation source missing', $this->context($service, $tenant, $webLocale));

            return $this->result($service, 'fallback', false, 'missing_source');
        }

        if ($webLocale === 'de') {
            return $this->result($this->applyTranslation($service, $source, 'de'), 'api', true);
        }

        Log::notice('Noindex German service fallback used', $this->context($service, $tenant, $webLocale) + [
            'reason' => 'translation_unavailable',
        ]);

        return $this->result(
            $this->applyTranslation($service, $source, 'de'),
            'fallback',
            false,
            'translation_unavailable'
        );
    }

    public function availableWebLocales(array $service, string $tenant): array
    {
        return collect(LocaleMapper::WEB_LOCALES)
            ->filter(function (string $locale) use ($service) {
                if (! $this->hasUsableTranslation($service, $locale)) {
                    return false;
                }

                return $this->sourceFaq($service) === [] || $this->localizedFaq($service, $locale) !== null;
            })
            ->values()
            ->all();
    }

    public function publicSlug(array $service, string $locale, ?string $fallback = null): string
    {
        $translation = $this->findTranslation($service, $locale);

        foreach ([$translation['slug'] ?? null, $fallback, $service['slug'] ?? null, $service['category_slug'] ?? null] as $slug) {
            $slug = trim((string) $slug, '/');

            if ($slug !== '') {
                return $slug;
            }
        }

        return '';
    }

    private function germanSource(array $service): ?array
    {
        $source = $this->findTranslation($service, 'de');

        if ($source !== null && $this->hasText($source['name'] ?? null) && $this->hasText($source['content'] ?? null, true)) {
            return $source;
        }

        $declared = LocaleMapper::toWeb(data_get($service, '_meta.default_language'));

        if ($declared === 'de' && $this->hasText($service['name'] ?? null) && $this->hasText($service['content'] ?? null, true)) {
            return collect(self::LOCALIZED_FIELDS)
                ->mapWithKeys(fn ($key) => [$key => $service[$key] ?? null])
                ->all();
        }

        return null;
    }

    private function findTranslation(array $service, string $locale): ?array
    {
        $webLocale = LocaleMapper::toWeb($locale);

        foreach (is_array($service['translations'] ?? null) ? $service['translations'] : [] as $translation) {
            if (is_array($translation)
                && LocaleMapper::toWeb((string) ($translation['language_code'] ?? $translation['locale'] ?? '')) === $webLocale) {
                return $translation;
            }
        }

        return null;
    }

    private function applyTranslation(array $service, array $translation, string $locale): array
    {
        $name = (string) ($translation['name'] ?? '');
        $content = (string) ($translation['content'] ?? '');
        $plainContent = trim(preg_replace('/\s+/u', ' ', strip_tags($content)) ?? '');
        $short = $this->hasText($translation['short_description'] ?? null)
            ? (string) $translation['short_description']
            : mb_substr($plainContent, 0, 180);
        $metaTitle = $this->hasText($translation['meta_title'] ?? null)
            ? (string) $translation['meta_title']
            : $name;
        $metaDescription = $this->hasText($translation['meta_description'] ?? null)
            ? (string) $translation['meta_description']
            : mb_substr($short ?: $plainContent, 0, 160);
        $metaKeywords = $this->hasText($translation['meta_keywords'] ?? null)
            ? (string) $translation['meta_keywords']
            : '';

        $service = array_merge($service, [
            'name' => $name,
            'content' => $content,
            'short_description' => $short,
            'meta_title' => $metaTitle,
            'meta_description' => $metaDescription,
            'meta_keywords' => $metaKeywords,
            'meta' => array_merge(is_array($service['meta'] ?? null) ? $service['meta'] : [], [
                'title' => $metaTitle,
                'description' => $metaDescription,
                'keywords' => $metaKeywords,
            ]),
        ]);

        $service['_resolved_locale'] = LocaleMapper::toWeb($locale);
        $service['translations'] = [[
            'language_code' => LocaleMapper::toApi($locale),
            ...collect(self::LOCALIZED_FIELDS)->mapWithKeys(fn ($key) => [$key => $service[$key] ?? null])->all(),
        ]];

        return $service;
    }

    private function sourceFaq(array $service): array
    {
        $source = $service['faq'] ?? $service['faqs'] ?? [];
        $items = is_array($source) && array_is_list($source) ? $source : ($source['items'] ?? $source['questions'] ?? []);

        return collect(is_array($items) ? $items : [])->filter(fn ($item) => is_array($item))->map(function ($item) {
            $de = $this->findTranslation($item, 'de') ?? [];

            return [
                'id' => $item['id'] ?? null,
                'question' => $de['question'] ?? $de['name'] ?? $item['question'] ?? null,
                'answer' => $de['answer'] ?? $de['content'] ?? $item['answer'] ?? null,
            ];
        })->filter(fn ($item) => $this->hasText($item['question']) && $this->hasText($item['answer'], true))->values()->all();
    }

    private function localizedFaq(array $service, string $locale): ?array
    {
        if ($locale === 'de') {
            return $this->sourceFaq($service);
        }

        $source = $service['faq'] ?? $service['faqs'] ?? [];
        $items = is_array($source) && array_is_list($source) ? $source : ($source['items'] ?? $source['questions'] ?? []);

        if (! is_array($items) || $items === []) {
            return [];
        }

        $localized = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($translation = $this->findTranslation($item, $locale))) {
                return null;
            }

            $question = $translation['question'] ?? $translation['name'] ?? null;
            $answer = $translation['answer'] ?? $translation['content'] ?? null;

            if (! $this->hasText($question) || ! $this->hasText($answer, true)) {
                return null;
            }

            $localized[] = ['id' => $item['id'] ?? null, 'question' => $question, 'answer' => $answer];
        }

        return $localized;
    }

    private function hasText(mixed $value, bool $html = false): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = $html ? strip_tags($value) : $value;

        return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? '') !== '';
    }

    private function normalizedContent(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_strtolower(trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $value) ?? ''));
    }

    private function result(array $service, string $origin, bool $indexable, ?string $reason = null): array
    {
        $service['_translation'] = [
            'origin' => $origin,
            'indexable' => $indexable,
            'fallback' => ! $indexable,
            'reason' => $reason,
        ];

        return $service;
    }

    private function context(array $service, string $tenant, string $locale): array
    {
        return ['tenant' => $tenant, 'service_id' => $service['id'] ?? null, 'locale' => $locale];
    }
}
