@extends('layouts.frontend')

@section('title', 'Алтех - Поставщик IT-решений для бизнеса')
@section('description', 'Алтех - поставщик и интегратор комплексных решений в области IT. Виртуализация, сборка серверов, рабочие станции, сетевые решения, безопасность.')

@section('content')
<!-- Hero Section -->
<div class="text-white relative overflow-hidden" style="background-color: #121212;">
    <!-- Анимированные звездочки -->
    <div id="stars-container" class="absolute inset-0 pointer-events-none"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 relative z-10">
        <div class="text-center">
            <h3 class="text-4xl md:text-4xl font-bold mb-6">
                <span class="text-gray-100">Алтех</span> — ИТ-решения для бизнеса
            </h3>
            <p class="text-base md:text-2xl mb-8 text-gray-200 max-w-3xl mx-auto">
                Поставщик и интегратор комплексных решений в области ИТ. Техническая поддержка, виртуализация, сборка серверов, рабочие станции, безопасность, обслуживание ИТ
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('services.index') }}" 
                   class="inline-flex items-center justify-center px-8 py-3 border border-transparent text-base font-medium rounded-lg text-black bg-white hover:bg-blue-100 transition-colors">
                    Наши услуги
                </a>
                <a href="#contact" 
                   class="inline-flex items-center justify-center px-8 py-3 border-2 border-white text-base font-medium rounded-lg text-white hover:bg-white hover:text-black transition-colors">
                    Связаться с нами
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.star {
    position: absolute;
    background-color: rgb(113, 190, 13);
    border-radius: 50%;
    box-shadow: 0 0 10px rgb(113, 190, 13), 0 0 20px rgb(113, 190, 13), 0 0 30px rgba(113, 190, 13, 0.5);
    animation: float 10s ease-in-out infinite;
    opacity: 0.8;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0); }
    25% { transform: translate(10px, -15px); }
    50% { transform: translate(-5px, -10px); }
    75% { transform: translate(-10px, 5px); }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('stars-container');
    const numStars = 15; // Количество звездочек
    
    function createStar() {
        const star = document.createElement('div');
        star.className = 'star';
        
        // Случайный размер от 3px до 8px
        const size = Math.random() * 5 + 3;
        star.style.width = size + 'px';
        star.style.height = size + 'px';
        
        // Случайная позиция
        star.style.left = Math.random() * 100 + '%';
        star.style.top = Math.random() * 100 + '%';
        
        // Случайная задержка анимации для плавания
        star.style.animationDelay = Math.random() * 10 + 's';
        star.style.animationDuration = (Math.random() * 5 + 8) + 's';
        
        return star;
    }
    
    // Создаем звездочки
    for (let i = 0; i < numStars; i++) {
        container.appendChild(createStar());
    }
});
</script>

<!-- Services Categories Section -->
@if($serviceCategories && $serviceCategories->count() > 0)
<div class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Наши направления
            </h2>
            <p class="text-xl text-gray-600">
                Полный спектр IT-услуг для вашего бизнеса
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($serviceCategories as $category)
                <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                    @if($category->image)
                        <div class="aspect-video overflow-hidden">
                            <img src="{{ url('storage/' . $category->image) }}" 
                                 alt="{{ $category->name }}" 
                                 class="w-full h-full object-cover">
                        </div>
                    @endif
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            @if($category->icon)
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <x-service-icon :icon="$category->icon" class="w-6 h-6 text-blue-600" />
                                    </div>
                                </div>
                            @else
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                            @endif
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $category->name }}
                                </h3>
                                @php
                                    $count = $category->services_count ?? 0;
                                    $serviceText = function_exists('services_count_text') ? services_count_text($count) : ($count > 0 ? "$count услуг" : '');
                                @endphp
                                @if(!empty($serviceText))
                                    <p class="text-sm text-gray-500">
                                        {{ $serviceText }}
                                    </p>
                                @endif
                            </div>
                        </div>
                        
                        @if($category->description)
                            <p class="text-gray-600 mb-4">
                                {{ Str::limit($category->description, 100) }}
                            </p>
                        @endif
                        
                        <a href="{{ route('services.category', $category->slug) }}" 
                           class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                            Подробнее
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="text-center mt-10">
            <a href="{{ route('services.index') }}" 
               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-900 transition-colors">
                Все услуги
            </a>
        </div>
    </div>
</div>
@else
<!-- Default Services Section when no categories exist -->
<div class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Наши направления
            </h2>
            <p class="text-xl text-gray-600">
                Полный спектр IT-услуг для вашего бизнеса
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- Техническая поддержка -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Техническая поддержка</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Круглосуточная поддержка вашей IT-инфраструктуры, удаленное администрирование и решение технических проблем.
                    </p>
                </div>
            </div>

            <!-- Виртуализация -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Виртуализация</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Внедрение и настройка виртуальных серверов, создание отказоустойчивых кластеров и оптимизация ресурсов.
                    </p>
                </div>
            </div>

            <!-- Сборка серверов -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Сборка серверов</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Проектирование и сборка серверных решений под ваши задачи, настройка и ввод в эксплуатацию.
                    </p>
                </div>
            </div>

            <!-- Рабочие станции -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Сборка рабочих станций</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Сборка и настройка рабочих станций для офиса, специализированных ПК для дизайна и инженерии.
                    </p>
                </div>
            </div>

            <!-- Сетевые решения -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01m-7.08-7.071c3.904-3.905 10.236-3.905 14.141 0M1.394 9.393c5.857-5.857 15.355-5.857 21.213 0"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Сетевые решения</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Проектирование и настройка корпоративных сетей, системы мониторинга и управления трафиком.
                    </p>
                </div>
            </div>

            <!-- Информационная безопасность -->
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">Информационная безопасность</h3>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">
                        Аудит безопасности, внедрение систем защиты информации и обучение персонала.
                    </p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-10">
            <a href="{{ route('services.index') }}" 
               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-900 transition-colors">
                Все услуги
            </a>
        </div>
    </div>
</div>
@endif

<!-- About Section -->
<div class="bg-gray-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Почему выбирают Алтех?
            </h2>
            <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                Мы используем современные подходы, быстро реагируем на потребности клиентов и обеспечиваем персональный подход к каждому проекту
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-900/5 text-center hover:shadow-md transition-shadow">
                <div class="text-4xl font-bold text-blue-600 mb-3">50+</div>
                <div class="text-gray-900 font-semibold mb-2">Довольных клиентов</div>
                <div class="text-sm text-gray-500">Успешно завершенные проекты</div>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-900/5 text-center hover:shadow-md transition-shadow">
                <div class="text-4xl font-bold text-blue-600 mb-3">24/7</div>
                <div class="text-gray-900 font-semibold mb-2">Техническая поддержка</div>
                <div class="text-sm text-gray-500">Круглосуточная помощь</div>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-900/5 text-center hover:shadow-md transition-shadow">
                <div class="text-4xl font-bold text-blue-600 mb-3">3+</div>
                <div class="text-gray-900 font-semibold mb-2">Года развития</div>
                <div class="text-sm text-gray-500">Опыт в IT-сфере</div>
            </div>
            <div class="bg-white p-8 rounded-xl shadow-sm ring-1 ring-gray-900/5 text-center hover:shadow-md transition-shadow">
                <div class="text-4xl font-bold text-blue-600 mb-3">100%</div>
                <div class="text-gray-900 font-semibold mb-2">Подход к качеству</div>
                <div class="text-sm text-gray-500">Внимание к деталям</div>
            </div>
        </div>
    </div>
</div>

<!-- Pages Section -->
@if($pages && $pages->count() > 0)
<div class="bg-white py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                О компании
            </h2>
            <p class="text-xl text-gray-600">
                Дополнительная информация о нашей компании
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($pages as $page)
                <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-2">{{ $page->title }}</h3>
                        <p class="text-gray-600 mb-4">
                            {{ Str::limit(strip_tags($page->content), 120) }}
                        </p>
                        <a href="{{ route('pages.show', $page->slug) }}" 
                           class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                            Читать далее
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- Contact Section -->
<div id="contact" class="bg-blue-50 py-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">
                Свяжитесь с нами
            </h2>
            <p class="text-xl text-gray-600">
                Готовы обсудить ваш IT-проект? Мы всегда рады помочь!
            </p>
        </div>

        <div class="flex justify-center">
            <!-- Contact Info -->
            <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-900/5 p-8 max-w-md w-full">
                <h3 class="text-xl font-semibold mb-6 text-center">Контактная информация</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        <span class="text-gray-700">office@rualtech.ru</span>
                    </div>
                    <div class="flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span class="text-gray-700">+7 (495) 275-35-33</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
