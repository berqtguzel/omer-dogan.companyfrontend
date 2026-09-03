<?php

namespace App\Http\Controllers;

use App\Support\LocaleMapper;
use App\Support\Omr\OmrCachedClient;
use App\Support\OmrConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class BlogController extends Controller
{
    private array $allowedLocales = LocaleMapper::WEB_LOCALES;

    private function locale(?string $locale = null): string
    {
        $locale = LocaleMapper::toWeb($locale ?: request()->route('locale') ?: session('locale') ?: OmrConfig::defaultLocale());

        if (! in_array($locale, $this->allowedLocales, true)) {
            $locale = OmrConfig::defaultLocale();
        }

        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $locale;
    }

    public function index()
    {
        $locale = $this->locale();
        $category = trim((string) request('category'));
        $response = $this->fetchPosts($locale, $category ?: null);
        $page = StaticPageController::getPage($locale, 'blog');

        return Inertia::render('Blog/Index', [
            'locale' => $locale,
            'page' => $page,
            'posts' => collect($response['data'] ?? [])->map(fn ($post) => $this->summary($post))->values()->all(),
            'categories' => $this->fetchCategories($locale),
            'selectedCategory' => $category ?: null,
            'pagination' => $response['meta'] ?? [],
        ]);
    }

    public function show()
    {
        $localeCandidate = request()->route('locale');
        $identifier = request()->route('slug');
        $category = request()->route('category');
        $locale = $this->locale($localeCandidate);
        $category = trim((string) (request('category') ?: $category));
        $post = $this->fetchPost($locale, (string) $identifier, $category ?: null);

        abort_unless($post, 404);

        $relatedPosts = collect($post['linked_posts'] ?? $post['related_posts'] ?? [])
            ->map(fn ($item) => $this->summary($this->normalizePost($item, $locale)))
            ->values()
            ->all();

        return Inertia::render('Blog/Show', [
            'locale' => $locale,
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }

    private function fetchPosts(string $locale, ?string $category = null): array
    {
        $tenant = OmrConfig::tenantId();
        $cacheKey = 'blog_posts_v4_'.md5($tenant.'|'.$locale.'|'.($category ?: 'all').'|'.(string) request('page', 1));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($locale, $category) {
            $path = $category
                ? 'blog-posts/categories/'.rawurlencode($category)
                : 'blog-posts';

            $json = $this->apiGet($path, $locale, [
                'category' => $category,
                'page' => request('page', 1),
            ]);

            return [
                'data' => collect($json['data'] ?? [])
                    ->map(fn ($post) => $this->normalizePost($post, $locale))
                    ->values()
                    ->all(),
                'meta' => $json['meta'] ?? [],
                'links' => $json['links'] ?? [],
            ];
        });
    }

    private function fetchPost(string $locale, string $identifier, ?string $category = null): ?array
    {
        $tenant = OmrConfig::tenantId();
        $cacheKey = 'blog_post_v4_'.md5($tenant.'|'.$locale.'|'.$identifier.'|'.($category ?: 'none'));

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($locale, $identifier, $category) {
            $json = $this->apiGet('blog-posts/'.rawurlencode($identifier), $locale, [
                'category' => $category,
            ]);

            $data = $json['data'] ?? null;

            if (is_array($data)) {
                return $this->normalizePost($data, $locale);
            }

            return $this->findPostInLists($locale, $identifier, $category);
        });
    }

    private function findPostInLists(string $locale, string $identifier, ?string $category = null): ?array
    {
        $candidates = [];

        if ($category) {
            $candidates[] = $category;
        }

        $firstSegment = explode('-', $identifier)[0] ?? '';

        if ($firstSegment !== '') {
            $candidates[] = $firstSegment;
        }

        $candidates[] = null;

        foreach (array_unique($candidates) as $candidateCategory) {
            $response = $this->fetchPosts($locale, $candidateCategory);
            $match = collect($response['data'] ?? [])->first(function ($post) use ($identifier) {
                return $this->postMatchesIdentifier($post, $identifier);
            });

            if ($match) {
                return $match;
            }
        }

        return null;
    }

    private function postMatchesIdentifier(array $post, string $identifier): bool
    {
        $identifier = trim($identifier, '/');
        $slug = trim((string) ($post['slug'] ?? ''), '/');
        $path = trim((string) ($post['path'] ?? ''), '/');
        $id = (string) ($post['id'] ?? '');

        return $identifier !== ''
            && ($identifier === $slug
                || $identifier === $path
                || str_ends_with($path, '/'.$identifier)
                || $identifier === $id);
    }

    private function fetchCategories(string $locale): array
    {
        $tenant = OmrConfig::tenantId();
        $cacheKey = "blog_categories_v2_{$tenant}_{$locale}";

        return Cache::remember($cacheKey, now()->addDays(30), function () use ($locale) {
            $json = $this->apiGet('blog-posts/categories', $locale);

            return collect($json['data'] ?? [])
                ->map(fn ($category) => $this->normalizeCategory($category))
                ->filter(fn ($category) => ! empty($category['slug']))
                ->values()
                ->all();
        });
    }

    private function apiGet(string $path, string $locale, array $params = []): array
    {
        $tenant = OmrConfig::tenantId();

        $response = OmrCachedClient::get('blog', $path, array_filter([
            'locale' => LocaleMapper::toApi($locale),
            'lang' => LocaleMapper::toApi($locale),
            ...$params,
        ], fn ($value) => $value !== null && $value !== ''), $tenant, [
            'connect_timeout' => 3,
            'timeout' => 8,
            'success_ttl' => now()->addDays(30),
            'stale_ttl' => now()->addDays(120),
            'failure_ttl' => now()->addMinutes(10),
            'rate_limit_ttl' => now()->addMinutes(30),
            'cooldown_minutes' => 30,
        ]);

        if (! ($response['ok'] ?? false)) {
            Log::warning('Blog API cached response unavailable', [
                'path' => $path,
                'status' => $response['status'] ?? null,
            ]);

            return [];
        }

        return is_array($response['json'] ?? null) ? $response['json'] : [];
    }

    private function normalizePost(array $post, string $locale): array
    {
        $meta = $post['meta'] ?? $post['seo'] ?? [];
        $category = $this->normalizeCategory($post['category'] ?? [
            'slug' => $post['category_slug'] ?? null,
            'name' => $post['category_name'] ?? null,
        ]);
        $content = $this->pick($post, ['content', 'body', 'html', 'description']);
        $excerpt = $this->pick($post, ['excerpt', 'summary', 'short_description', 'meta_description'])
            ?: $this->excerpt($content);
        $title = $this->pick($post, ['title', 'name', 'meta_title', 'headline']) ?: ($post['slug'] ?? 'Blog');
        $slug = (string) ($post['slug'] ?? $post['path'] ?? $post['id'] ?? '');

        return [
            'id' => $post['id'] ?? $slug,
            'slug' => $slug,
            'path' => $post['path'] ?? null,
            'title' => $title,
            'excerpt' => $excerpt,
            'category' => $category,
            'tags' => $this->normalizeTags($post['tags'] ?? []),
            'city' => $post['city'] ?? null,
            'published_at' => $post['published_at'] ?? $post['publishedAt'] ?? $post['created_at'] ?? null,
            'reading_time' => $post['reading_time'] ?? $post['readingTime'] ?? $post['read_time'] ?? null,
            'seo_score' => $post['seo_score'] ?? null,
            'nlp_score' => $post['nlp_score'] ?? null,
            'image' => $post['image'] ?? $post['image_url'] ?? $post['cover_image'] ?? $post['media']['url'] ?? null,
            'meta' => [
                'title' => $meta['title'] ?? $meta['meta_title'] ?? $post['meta_title'] ?? $title,
                'description' => $meta['description'] ?? $meta['meta_description'] ?? $post['meta_description'] ?? $excerpt,
                'keywords' => $meta['keywords'] ?? $meta['meta_keywords'] ?? $post['meta_keywords'] ?? null,
            ],
            'primary_service' => $this->normalizeService($post['primary_service'] ?? $post['service'] ?? []),
            'linked_services' => collect($post['linked_services'] ?? [])->map(fn ($service) => $this->normalizeService($service))->values()->all(),
            'content' => $content,
            'faq_id' => $post['faq_id'] ?? null,
            'faq' => $this->normalizeFaq($post['faq'] ?? [], $locale),
            'linked_posts' => $post['linked_posts'] ?? $post['related_posts'] ?? [],
        ];
    }

    private function normalizeCategory(array|string|null $category): array
    {
        if (is_string($category)) {
            return ['slug' => $category, 'name' => $category];
        }

        $category = is_array($category) ? $category : [];

        return [
            'slug' => (string) ($category['slug'] ?? $category['category_slug'] ?? $category['id'] ?? ''),
            'name' => (string) ($category['name'] ?? $category['title'] ?? $category['label'] ?? $category['slug'] ?? ''),
        ];
    }

    private function normalizeTags(array|string|null $tags): array
    {
        if (is_string($tags)) {
            $tags = array_filter(array_map('trim', explode(',', $tags)));
        }

        return collect($tags ?: [])
            ->map(fn ($tag) => is_array($tag) ? ($tag['name'] ?? $tag['title'] ?? $tag['slug'] ?? null) : $tag)
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeService(array|string|null $service): array
    {
        if (is_string($service)) {
            return ['slug' => $service, 'name' => $service];
        }

        $service = is_array($service) ? $service : [];

        return [
            'slug' => (string) ($service['slug'] ?? $service['path'] ?? $service['id'] ?? ''),
            'name' => (string) ($service['name'] ?? $service['title'] ?? $service['label'] ?? $service['slug'] ?? ''),
            'category_slug' => $service['category_slug'] ?? null,
        ];
    }

    private function normalizeFaq(array $faq, string $locale): array
    {
        $items = $faq['items'] ?? $faq['questions'] ?? [];

        return [
            'title' => $faq['title'] ?? $faq['name'] ?? 'FAQ',
            'items' => collect($items)
                ->map(function ($item, $index) {
                    return [
                        'id' => $item['id'] ?? $index,
                        'question' => $item['question'] ?? $item['title'] ?? '',
                        'answer' => $item['answer'] ?? $item['content'] ?? $item['body'] ?? '',
                    ];
                })
                ->filter(fn ($item) => $item['question'] !== '' && $item['answer'] !== '')
                ->values()
                ->all(),
        ];
    }

    private function summary(array $post): array
    {
        return collect($post)
            ->only([
                'id',
                'slug',
                'path',
                'title',
                'excerpt',
                'category',
                'tags',
                'city',
                'published_at',
                'reading_time',
                'seo_score',
                'nlp_score',
                'image',
            ])
            ->all();
    }

    private function pick(array $data, array $keys): string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
                return trim($data[$key]);
            }
        }

        return '';
    }

    private function excerpt(?string $content): string
    {
        return trim(mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) $content)), 0, 180));
    }
}
