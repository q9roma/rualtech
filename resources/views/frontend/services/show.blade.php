@extends('layouts.frontend')

@section('title', $service->seo_title ?: $service->name . ' - ' . $category->name . ' | Altech')
@section('description', $service->seo_description ?: $service->short_description)

@section('content')
<!-- Breadcrumbs -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="flex items-center space-x-4">
                <li>
                    <a href="{{ route('home') }}" class="text-gray-500 hover:text-gray-700">
                        <svg class="flex-shrink-0 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('services.index') }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            Услуги
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('services.category', $category->slug) }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            {{ $category->name }}
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-gray-500">{{ $service->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
</div>

<!-- Service Header -->
<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Service Info -->
            <div class="lg:col-span-8">
                <div class="flex items-start">
                    @if($service->icon)
                        <div class="flex-shrink-0 mr-4">
                            <div class="w-16 h-16 bg-blue-100 rounded-xl flex items-center justify-center">
                                <x-service-icon :icon="$service->icon" class="w-8 h-8 text-blue-600" />
                            </div>
                        </div>
                    @endif
                    <div class="flex-1">
                        <div class="flex items-center">
                            <h1 class="text-3xl font-bold text-gray-900">
                                {{ $service->name }}
                            </h1>
                            @if($service->is_featured)
                                <span class="ml-3 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    Рекомендуем
                                </span>
                            @endif
                        </div>
                        <p class="mt-2 text-lg text-gray-600">
                            {{ $service->short_description }}
                        </p>
                        <div class="mt-2 flex items-center text-sm text-gray-500">
                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                {{ $category->name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Price Card -->
            <div class="mt-8 lg:mt-0 lg:col-span-4">
                <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Стоимость услуги</h3>
                    </div>
                    <div class="px-6 py-4">
                        @if($service->price_from)
                            <div class="text-center">
                                <p class="text-3xl font-bold text-gray-900">
                                    {{ number_format($service->price_from, 0, ',', ' ') }} ₽
                                </p>
                                <p class="text-sm text-gray-500">{{ $service->price_type }}</p>
                            </div>
                        @else
                            <div class="text-center">
                                <p class="text-xl font-semibold text-gray-900">По запросу</p>
                                <p class="text-sm text-gray-500">Индивидуальный расчет</p>
                            </div>
                        @endif
                        
                        <div class="mt-6">
                            <a href="#contact" 
                               class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Заказать услугу
                            </a>
                        </div>
                        
                        <div class="mt-3">
                            <a href="#" 
                               class="w-full flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Получить консультацию
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Service Content -->
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="lg:grid lg:grid-cols-12 lg:gap-8">
            <!-- Description -->
            <div class="lg:col-span-8">
                <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Описание услуги</h2>
                    </div>
                    <div class="px-6 py-6">
                        <div class="prose prose-blue max-w-none">
                            {!! $service->description !!}
                        </div>
                    </div>
                </div>

                @if($service->features && count($service->features) > 0)
                    <div class="mt-6 bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-900">Особенности</h2>
                        </div>
                        <div class="px-6 py-6">
                            <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                @foreach($service->features as $feature)
                                    <li class="flex items-center">
                                        <svg class="flex-shrink-0 h-5 w-5 text-blue-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-sm text-gray-700">{{ $feature }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Sidebar -->
            <div class="mt-8 lg:mt-0 lg:col-span-4 space-y-6">
                <!-- Contact Card -->
                <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Нужна помощь?</h3>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-gray-600 mb-4">
                            Наши специалисты ответят на все вопросы и помогут подобрать оптимальное решение
                        </p>
                        <div class="space-y-3">
                            <div class="flex items-center text-sm text-gray-700">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                </svg>
                                +7 (495) 123-45-67
                            </div>
                            <div class="flex items-center text-sm text-gray-700">
                                <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                info@altech.ru
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Services -->
                @if($relatedServices->count() > 0)
                    <div class="bg-white shadow-sm ring-1 ring-gray-900/5 rounded-xl overflow-hidden">
                        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">Другие услуги категории</h3>
                        </div>
                        <div class="px-6 py-4">
                            <ul class="space-y-3">
                                @foreach($relatedServices as $relatedService)
                                    <li>
                                        <a href="{{ route('services.show', [$category->slug, $relatedService->slug]) }}" 
                                           class="block p-2 -mx-2 rounded-lg hover:bg-gray-50 transition-colors">
                                            <p class="text-sm font-medium text-gray-900 hover:text-blue-600">
                                                {{ $relatedService->name }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ Str::limit($relatedService->short_description, 60) }}
                                            </p>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <a href="{{ route('services.category', $category->slug) }}" 
                                   class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                    Все услуги категории →
                                </a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
