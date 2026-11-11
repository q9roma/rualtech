<?php

return [
    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'timeout' => env('APP_ENV') === 'local' ? 15 : 2,
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => true,
        'throw_on_error' => (bool) env('INERTIA_SSR_THROW_ON_ERROR', false),
    ],
    'pages' => [
        'ensure_pages_exist' => true,
        'paths' => [resource_path('js/pages')],
        'extensions' => ['tsx'],
    ],
    'testing' => ['ensure_pages_exist' => true],
];
