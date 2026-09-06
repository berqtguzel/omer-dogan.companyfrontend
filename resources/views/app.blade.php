@php
    $props = $page['props'] ?? [];
    $inertiaLocale = $props['locale'] ?? ($props['global']['locale'] ?? null);
    $htmlLocale = $inertiaLocale ?: app()->getLocale();
    $siteName = data_get($props, 'siteShell.name', config('corporate_home.site_name'));
    $labels = [
        'de' => ['group' => 'Unternehmensgruppe', 'contact' => 'Kontakt', 'description' => 'Offizielle Website der '.$siteName.'.'],
        'en' => ['group' => 'Corporate Group', 'contact' => 'Contact', 'description' => 'Official website of '.$siteName.'.'],
        'tr' => ['group' => 'Şirketler Grubu', 'contact' => 'İletişim', 'description' => $siteName.' resmi web sitesi.'],
    ];
    $label = $labels[$htmlLocale] ?? $labels['de'];
    $component = $page['component'] ?? '';
    $document = $props['document'] ?? [];
    $catalog = $props['catalog'] ?? [];
    $catalogItem = $catalog['item'] ?? null;
    $home = $props['home'] ?? [];
    $serverTitle = data_get($document, 'seo.title')
        ?: data_get($document, 'title')
        ?: data_get($catalogItem, 'title')
        ?: data_get($catalogItem, 'name')
        ?: data_get($catalog, 'title')
        ?: data_get($home, 'seo.title');
    if (! $serverTitle) {
        $serverTitle = $component === 'kontakt/index' ? $label['contact'] : $siteName.' | '.$label['group'];
    }
    if ($component === 'Corporate/Catalog' && $serverTitle && $siteName) {
        $serverTitle .= ' | '.$siteName;
    }
    if (str_starts_with($component, 'Errors/')) {
        $serverTitle = ($props['status'] ?? '404').' - '.($props['title'] ?? $serverTitle);
    }
    $serverDescription = data_get($document, 'seo.description')
        ?: data_get($catalogItem, 'description')
        ?: data_get($catalog, 'description')
        ?: data_get($home, 'seo.description')
        ?: data_get($home, 'hero.description')
        ?: data_get($props, 'siteShell.description')
        ?: $label['description'];
    $serverCanonical = data_get($props, 'tenantSeo.canonicalUrl', '');
    $canonicalBase = rtrim((string) data_get($props, 'tenantSeo.canonicalBaseUrl', ''), '/');
    $serverNoindex = ($props['corporateReady'] ?? false) === false
        || str_starts_with($component, 'Errors/')
        || ($component === 'StaticPage' && ! data_get($document, 'available', false))
        || ($component === 'Corporate/Catalog' && ! $catalogItem && empty($catalog['items'] ?? []));
    $serverAlternates = $document['alternates'] ?? ($catalogItem['alternates'] ?? []);
    if (! $serverNoindex && $canonicalBase && in_array($component, ['Home', 'kontakt/index'], true)) {
        $suffix = $component === 'Home' ? '/' : '/kontakt';
        $serverAlternates = collect($props['languages'] ?? [])
            ->pluck('code')->filter(fn ($code) => preg_match('/^[a-z]{2}$/', (string) $code))
            ->mapWithKeys(fn ($code) => [$code => '/'.$code.$suffix])->all();
    }
    $schemas = [];
    if (! $serverNoindex && $serverCanonical && $canonicalBase) {
        $organization = ['@type' => 'Organization', '@id' => $canonicalBase.'/#organization', 'name' => $siteName, 'url' => $canonicalBase];
        if (data_get($props, 'siteShell.logo')) $organization['logo'] = data_get($props, 'siteShell.logo');
        if (data_get($props, 'siteShell.email')) $organization['email'] = data_get($props, 'siteShell.email');
        if (data_get($props, 'siteShell.phone')) $organization['telephone'] = data_get($props, 'siteShell.phone');
        $schemas[] = $organization;
        $schemas[] = ['@type' => 'WebSite', '@id' => $canonicalBase.'/#website', 'name' => $siteName, 'url' => $canonicalBase, 'publisher' => ['@id' => $canonicalBase.'/#organization']];
        if ($component === 'kontakt/index') {
            $schemas[] = ['@type' => 'ContactPage', '@id' => $serverCanonical.'#webpage', 'name' => $serverTitle, 'url' => $serverCanonical, 'isPartOf' => ['@id' => $canonicalBase.'/#website']];
        }
        if ($component === 'StaticPage' || $component === 'Corporate/Catalog') {
            $schemas[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => $siteName, 'item' => $canonicalBase.'/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $serverTitle, 'item' => $serverCanonical],
            ]];
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLocale }}"
      data-locale="{{ $htmlLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia="title">{{ $serverTitle }}</title>
    @if($serverDescription)
        <meta inertia="description" name="description" content="{{ $serverDescription }}">
    @endif
    <meta inertia="robots" name="robots" content="{{ $serverNoindex ? 'noindex, follow' : 'index, follow' }}">
    @if(! $serverNoindex && $serverCanonical)
        <link inertia="canonical" rel="canonical" href="{{ $serverCanonical }}">
        <meta inertia="og:url" property="og:url" content="{{ $serverCanonical }}">
    @endif
    @if(! $serverNoindex && $canonicalBase)
        @foreach($serverAlternates as $alternateCode => $alternatePath)
            @if(is_string($alternatePath) && str_starts_with($alternatePath, '/'))
                <link inertia="alternate-{{ $alternateCode }}" rel="alternate" hreflang="{{ $alternateCode }}" href="{{ $canonicalBase.$alternatePath }}">
            @endif
        @endforeach
        @if(isset($serverAlternates['de']) && is_string($serverAlternates['de']) && str_starts_with($serverAlternates['de'], '/'))
            <link inertia="alternate-x-default" rel="alternate" hreflang="x-default" href="{{ $canonicalBase.$serverAlternates['de'] }}">
        @endif
    @endif
    <meta inertia="og:title" property="og:title" content="{{ $serverTitle }}">
    @if($serverDescription)
        <meta inertia="og:description" property="og:description" content="{{ $serverDescription }}">
    @endif
    <meta inertia="og:type" property="og:type" content="website">
    <meta inertia="og:locale" property="og:locale" content="{{ $htmlLocale }}">
    <meta inertia="twitter:card" name="twitter:card" content="summary">
    <meta inertia="twitter:title" name="twitter:title" content="{{ $serverTitle }}">
    @if($serverDescription)
        <meta inertia="twitter:description" name="twitter:description" content="{{ $serverDescription }}">
    @endif
    @if($schemas)
        <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $schemas], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
    @endif
    <link rel="manifest" href="/manifest.webmanifest">
    {{-- Vite --}}
    @viteReactRefresh
    @vite([
        'resources/js/app.jsx',
    ])

    @inertiaHead
</head>

<body class="font-sans antialiased bg-white">
    @inertia
</body>
</html>
