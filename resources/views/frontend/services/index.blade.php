@extends('layouts.frontend')

@section('title', 'Услуги - IT-решения для бизнеса | Altech')
@section('description', 'Полный спектр IT-услуг: виртуализация, сборка серверов, техническая поддержка, сетевые решения и информационная безопасность.')

@section('content')
<!-- Header Section -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:truncate sm:text-3xl">
                    Наши услуги
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Комплексные IT-решения для вашего бизнеса
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Services Grid -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if($categories->count() > 0)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
            @foreach($categories as $category)
                <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow group">
                    @if($category->image)
                        <div class="aspect-video overflow-hidden">
                            <img src="{{ asset('storage/' . $category->image) }}" 
                                 alt="{{ $category->name }}" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        </div>
                    @endif
                    
                    <!-- Category Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <div class="flex items-center">
                            @if($category->icon)
                                <div class="flex-shrink-0">
                                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <x-service-icon :icon="$category->icon" class="w-5 h-5 text-blue-600" />
                                    </div>
                                </div>
                            @endif
                            <div class="ml-4 flex-1">
                                <h3 class="text-lg font-semibold text-gray-900">
                                    {{ $category->name }}
                                </h3>
                                @if($category->description)
                                    <p class="text-sm text-gray-500">
                                        {{ Str::limit($category->description, 60) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Services List -->
                    <div class="px-6 py-4">
                        @if($category->activeServices->count() > 0)
                            <ul class="space-y-3">
                                @foreach($category->activeServices->take(5) as $service)
                                    <li>
                                        <a href="{{ route('services.show', [$category->slug, $service->slug]) }}" 
                                           class="group flex items-center justify-between p-2 -mx-2 rounded-lg hover:bg-gray-50 transition-colors">
                                            <div class="flex-1">
                                                <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600">
                                                    {{ $service->name }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    {{ Str::limit($service->short_description, 50) }}
                                                </p>
                                            </div>
                                            @if($service->price_from)
                                                <div class="ml-2 flex-shrink-0">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                                        {{ $service->formatted_price }}
                                                    </span>
                                                </div>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>

                            @if($category->activeServices->count() > 5)
                                <div class="mt-4 pt-4 border-t border-gray-200">
                                    <p class="text-xs text-gray-500 text-center">
                                        +{{ $category->activeServices->count() - 5 }} {{ Str::plural('услуга', $category->activeServices->count() - 5, ['услуга', 'услуги', 'услуг']) }}
                                    </p>
                                </div>
                            @endif
                        @else
                            <p class="text-sm text-gray-500 italic">
                                Услуги в разработке
                            </p>
                        @endif
                    </div>

                    <!-- Category Footer -->
                    <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                        <a href="{{ route('services.category', $category->slug) }}" 
                           class="text-sm font-medium text-blue-600 hover:text-blue-800 flex items-center">
                            Все услуги категории
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Empty State -->
        <div class="text-center py-12">
            <div class="mx-auto h-12 w-12 text-gray-400">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
            </div>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Нет услуг</h3>
            <p class="mt-1 text-sm text-gray-500">
                Услуги находятся в разработке. Свяжитесь с нами для получения консультации.
            </p>
        </div>
    @endif
</div>

<!-- CTA Section -->
<div class="bg-blue-50 border-t border-blue-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Не нашли нужную услугу?
            </h2>
            <p class="text-lg text-gray-600 mb-6">
                Мы поможем подобрать индивидуальное решение для вашего бизнеса
            </p>
            <a href="#contact" 
               class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-900 transition-colors">
                Получить консультацию
            </a>
        </div>
    </div>
</div>
@endsection
