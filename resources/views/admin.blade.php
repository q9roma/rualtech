<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>try { if (localStorage.getItem('admin-theme') === 'dark') document.documentElement.classList.add('dark'); } catch (e) {}</script>
    @viteReactRefresh
    @vite('resources/js/admin.tsx')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
