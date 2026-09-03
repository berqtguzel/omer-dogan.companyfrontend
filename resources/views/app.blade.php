@php
    $inertiaLocale = $page['props']['locale'] ?? ($page['props']['global']['locale'] ?? null);
    $htmlLocale = $inertiaLocale ?: app()->getLocale();
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLocale }}"
      data-locale="{{ $htmlLocale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="/manifest.webmanifest">
    <style>
        .site-header svg,.footer svg,.service-card__arrow,.location-card-arrow{width:1.15rem;height:1.15rem;max-width:1.25rem;max-height:1.25rem;flex-shrink:0}
        .nav__chev,.submenu__arrow{width:.875rem;height:.875rem;font-size:.875rem}
    </style>

    {{-- Performance DNS Prefetch --}}
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    {{-- Vite --}}
    @routes
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
