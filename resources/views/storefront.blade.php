<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <style>
        .storefront-booting [data-fallback-content] { display: none; }
        .storefront-loading { display: none; }
        .storefront-booting .storefront-loading {
            min-height: 70vh; display: flex; align-items: center; justify-content: center;
            flex-direction: column; gap: 20px; color: #171717; background: #fff;
            font: 15px/1.5 Arial, sans-serif;
        }
        .storefront-loading-indicator {
            width: 28px; height: 28px; border: 2px solid #e5e5e5;
            border-top-color: #171717; border-radius: 50%;
            animation: storefront-spin .8s linear infinite;
        }
        @keyframes storefront-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .storefront-loading-indicator { animation: none; } }
    </style>
    <script>
        document.documentElement.classList.add('storefront-booting');
        // Restore the readable fallback if the application bundle cannot start.
        window.setTimeout(function () {
            document.documentElement.classList.remove('storefront-booting');
        }, 8000);
    </script>
    @viteReactRefresh
    @vite(['resources/css/storefront-entry.css', 'resources/js/storefront.tsx'])
    @inertiaHead
    @if(!$__inertiaSsrResponse && in_array($page['component'] ?? '', ['home', 'catalog/index', 'products/show', 'services/index', 'services/directions', 'services/direction', 'services/category', 'services/show', 'blog/index', 'blog/show', 'page', 'delivery']))
        <title data-inertia="">{{ $page['props']['seoTitle'] ?? $page['props']['title'] ?? 'Алтех' }} — Алтех</title>
        <meta data-inertia="" name="description" content="{{ $page['props']['description'] ?? '' }}">
        <meta data-inertia="" property="og:type" content="website">
        <meta data-inertia="" property="og:site_name" content="Алтех">
        <meta data-inertia="" property="og:title" content="{{ $page['props']['seoTitle'] ?? $page['props']['title'] ?? 'Алтех' }}">
        <meta data-inertia="" property="og:description" content="{{ $page['props']['description'] ?? '' }}">
        <meta data-inertia="" property="og:url" content="{{ $page['props']['canonicalUrl'] }}">
        <link data-inertia="" rel="canonical" href="{{ $page['props']['canonicalUrl'] }}">
        @if(!empty($page['props']['structuredData']))
        <script data-inertia="" type="application/ld+json">{!! json_encode($page['props']['structuredData'] ?? [], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endif
</head>
<body>
    @if(!$__inertiaSsrResponse && in_array($page['component'] ?? '', ['home', 'catalog/index', 'products/show', 'services/index', 'services/directions', 'services/direction', 'services/category', 'services/show', 'blog/index', 'blog/show', 'page', 'delivery']))
        <script data-page="app" type="application/json">{!! json_encode($page, JSON_HEX_TAG) !!}</script>
        <div id="app" data-server-fallback>
            <div class="storefront-loading" role="status" aria-live="polite">
                <span class="storefront-loading-indicator" aria-hidden="true"></span>
                <span>Загружаем страницу…</span>
            </div>
            <div data-fallback-content>
            @if($page['component'] === 'products/show')
                @include('frontend.products.content', ['item' => $page['props']['product']])
            @else
                @include('frontend.search-content')
            @endif
            </div>
        </div>
    @else
        @inertia
    @endif
    @production
        @include('components.storefront-analytics')
    @endproduction
</body>
</html>


