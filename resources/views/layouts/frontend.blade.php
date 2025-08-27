<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO -->
    <title>@yield('title', 'Altech - IT-решения для бизнеса')</title>
    <meta name="description" content="@yield('description', 'Altech - поставщик и интегратор комплексных решений в области IT')">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'green': {
                            50: '#f1fdf3',
                            100: '#e0fce6',
                            200: '#c2f8ce',
                            300: '#94f1a8',
                            400: '#5ee479',
                            500: '#32d051',
                            600: '#71BE0D',
                            700: '#5a9b08',
                            800: '#4a7f07',
                            900: '#3a6306',
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js для интерактивности -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- Custom Styles -->
    <style>
        .prose {
            max-width: none;
            color: #374151;
        }
        .prose h1, .prose h2, .prose h3, .prose h4 {
            color: #1f2937;
            font-weight: 600;
            margin-top: 2em;
            margin-bottom: 1em;
        }
        .prose h1 { font-size: 2.25em; }
        .prose h2 { font-size: 1.875em; }
        .prose h3 { font-size: 1.5em; }
        .prose h4 { font-size: 1.25em; }
        .prose p {
            margin-bottom: 1.25em;
            line-height: 1.75;
        }
        .prose ul, .prose ol {
            margin: 1.25em 0;
            padding-left: 1.625em;
        }
        .prose li {
            margin: 0.5em 0;
        }
        .prose a {
            color: #2563eb;
            text-decoration: none;
        }
        .prose a:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }
        .prose img {
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            margin: 2em 0;
        }
        .prose blockquote {
            border-left: 4px solid #e5e7eb;
            padding-left: 1rem;
            font-style: italic;
            color: #6b7280;
            margin: 1.5em 0;
        }
        .prose code {
            background-color: #f3f4f6;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875em;
            color: #1f2937;
        }
        .prose pre {
            background-color: #1f2937;
            color: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            overflow-x: auto;
            margin: 1.5em 0;
        }
        .prose pre code {
            background-color: transparent;
            padding: 0;
            color: inherit;
        }
        .prose table {
            width: 100%;
            margin: 2em 0;
            border-collapse: collapse;
        }
        .prose th, .prose td {
            border: 1px solid #e5e7eb;
            padding: 0.75rem;
            text-align: left;
        }
        .prose th {
            background-color: #f9fafb;
            font-weight: 600;
        }
    </style>
    
    @stack('styles')
</head>
<body class="bg-gray-50 text-gray-900 pt-20">
    <!-- Header -->
    <header class="shadow-lg border-b border-gray-700 fixed top-0 left-0 right-0 z-50" style="background-color: #121212;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <!-- Logo -->
                <div class="flex items-center">
                    <a href="{{ route('home') }}" class="flex items-center">
                        <img src="{{ asset('images/logo_light.svg') }}" 
                             alt="Altech" 
                             class="h-12 w-auto">
                    </a>
                </div>

                <!-- Navigation Desktop -->
                <nav class="hidden md:flex space-x-1">
                    <a href="{{ route('home') }}" 
                       class="px-4 py-2 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all duration-200 font-medium {{ request()->routeIs('home') ? 'bg-green-600 text-white' : '' }}">
                        Главная
                    </a>
                    <a href="{{ route('services.index') }}" 
                       class="px-4 py-2 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all duration-200 font-medium {{ request()->routeIs('services.*') ? 'bg-green-600 text-white' : '' }}">
                        Услуги
                    </a>
                    <a href="{{ route('blog.index') }}" 
                       class="px-4 py-2 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all duration-200 font-medium {{ request()->routeIs('blog.*') ? 'bg-green-600 text-white' : '' }}">
                        Блог
                    </a>
                    @foreach($navigationPages ?? [] as $page)
                        <a href="{{ route('pages.show', $page->slug) }}" 
                           class="px-4 py-2 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all duration-200 font-medium {{ request()->url() === route('pages.show', $page->slug) ? 'bg-green-600 text-white' : '' }}">
                            {{ $page->title }}
                        </a>
                    @endforeach
                    <a href="#contact" class="px-4 py-2 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all duration-200 font-medium">
                        Контакты
                    </a>
                </nav>

                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button x-data @click="$dispatch('toggle-mobile-menu')" 
                            class="p-2 rounded-lg text-gray-300 hover:text-white hover:bg-green-800 transition-all">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                  d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Navigation -->
            <div x-data="{ open: false }" 
                 x-on:toggle-mobile-menu.window="open = !open"
                 x-show="open" 
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform -translate-y-1"
                 x-transition:enter-end="opacity-100 transform translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform translate-y-0"
                 x-transition:leave-end="opacity-0 transform -translate-y-1"
                 class="md:hidden pb-4 border-t border-gray-700"
                 style="display: none;">
                <div class="space-y-1 pt-4">
                    <a href="{{ route('home') }}" 
                       class="block px-4 py-3 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all font-medium {{ request()->routeIs('home') ? 'bg-green-600 text-white' : '' }}">
                        Главная
                    </a>
                    <a href="{{ route('services.index') }}" 
                       class="block px-4 py-3 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all font-medium {{ request()->routeIs('services.*') ? 'bg-green-600 text-white' : '' }}">
                        Услуги
                    </a>
                    <a href="{{ route('blog.index') }}" 
                       class="block px-4 py-3 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all font-medium {{ request()->routeIs('blog.*') ? 'bg-green-600 text-white' : '' }}">
                        Блог
                    </a>
                    @foreach($navigationPages ?? [] as $page)
                        <a href="{{ route('pages.show', $page->slug) }}" 
                           class="block px-4 py-3 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all font-medium {{ request()->url() === route('pages.show', $page->slug) ? 'bg-green-600 text-white' : '' }}">
                            {{ $page->title }}
                        </a>
                    @endforeach
                    <a href="#contact" class="block px-4 py-3 rounded-lg text-gray-300 hover:bg-green-800 hover:text-white transition-all font-medium">
                        Контакты
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="min-h-screen">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="text-white" style="background-color: #121212;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center mb-4">
                        <img src="{{ asset('images/logo_light.svg') }}" 
                             alt="Altech" 
                             class="h-8 w-auto mr-3">
                        <h3 class="text-lg font-semibold">Altech</h3>
                    </div>
                    <p class="text-gray-300">
                        Современные технологические решения для вашего бизнеса.
                    </p>
                </div>

                <!-- Navigation -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Навигация</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="{{ route('home') }}" class="text-gray-300 hover:text-green-600 transition-colors">
                                Главная
                            </a>
                        </li>
                        @foreach($navigationPages ?? [] as $page)
                            <li>
                                <a href="{{ route('pages.show', $page->slug) }}" 
                                   class="text-gray-300 hover:text-green-600 transition-colors">
                                    {{ $page->title }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <!-- Contact -->
                <div>
                    <h3 class="text-lg font-semibold mb-4">Контакты</h3>
                    <div class="space-y-2 text-gray-300">
                        <p>Email: info@altech.ru</p>
                        <p>Телефон: +7 (495) 123-45-67</p>
                        <p>Адрес: Москва, ул. Примерная, 123</p>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-600 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; {{ date('Y') }} Altech. Все права защищены.</p>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
