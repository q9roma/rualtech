@extends('layouts.frontend')

@section('title', $category->seo_title ?: $category->name . ' - Услуги | Altech')
@section('description', $category->seo_description ?: $category->description)

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
                        <span class="ml-4 text-sm font-medium text-gray-500">{{ $category->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
</div>

<!-- Category Header -->
<div class="relative bg-gray-900 overflow-hidden min-h-[40vh] md:min-h-[50vh] flex items-end">
    @if($category->image)
        <!-- Background Image -->
        <div class="absolute inset-0">
            <img src="{{ asset('storage/' . $category->image) }}" 
                 alt="{{ $category->name }}" 
                 class="w-full h-full object-cover blur-sm">
        </div>
        <!-- Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/90 via-gray-900/50 to-gray-900/20"></div>
    @else
        <!-- Fallback gradient background -->
        <div class="absolute inset-0 bg-gradient-to-r from-blue-900 to-blue-700"></div>
    @endif
    
    <!-- Content -->
    <div class="relative w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pb-12 md:pb-16">
        <div class="md:flex md:items-end md:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center">
                    @if($category->icon)
                        <div class="flex-shrink-0 mr-4">
                            <div class="w-14 h-14 bg-white/10 backdrop-blur-sm rounded-xl flex items-center justify-center border border-white/20">
                                <x-service-icon :icon="$category->icon" class="w-7 h-7 text-white" />
                            </div>
                        </div>
                    @endif
                    <div>
                        <h2 class="text-4xl font-bold leading-tight text-white sm:text-4xl md:text-4xl">
                            {{ $category->name }}
                        </h2>
                        @if($category->description)
                            <p class="mt-3 text-md text-gray-200 max-w-3xl">
                                {{ $category->description }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="mt-6 flex md:mt-0 md:ml-8">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-white/20 backdrop-blur-sm text-white border border-white/30">
                    @php
                        $total = $services->total();
                        $word = 'услуга';
                        if ($total % 10 == 1 && $total % 100 != 11) {
                            $word = 'услуга';
                        } elseif (in_array($total % 10, [2, 3, 4]) && !in_array($total % 100, [12, 13, 14])) {
                            $word = 'услуги';
                        } else {
                            $word = 'услуг';
                        }
                    @endphp
                    {{ $total }} {{ $word }}
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Services Grid -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    @if($services->count() > 0)
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach($services as $service)
                <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                    <div class="p-6">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center">
                                    @if($service->icon)
                                        <div class="flex-shrink-0 mr-3">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                                                <x-service-icon :icon="$service->icon" class="w-4 h-4 text-blue-600" />
                                            </div>
                                        </div>
                                    @endif
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        {{ $service->name }}
                                    </h3>
                                    @if($service->is_featured)
                                        <span class="ml-2 inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Рекомендуем
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-2 text-sm text-gray-600">
                                    {{ $service->short_description }}
                                </p>

                                @if($service->features && count($service->features) > 0)
                                    <div class="mt-3">
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($service->features, 0, 3) as $feature)
                                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                    {{ $feature }}
                                                </span>
                                            @endforeach
                                            @if(count($service->features) > 3)
                                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                                    +{{ count($service->features) - 3 }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>

                            @if($service->price_from)
                                <div class="ml-4 flex-shrink-0">
                                    <div class="text-right">
                                        <p class="text-lg font-semibold text-gray-900">
                                            {{ $service->formatted_price }}
                                        </p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <a href="{{ route('services.show', [$category->slug, $service->slug]) }}" 
                               class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Подробнее
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        @if($services->hasPages())
            <div class="mt-8">
                {{ $services->links() }}
            </div>
        @endif
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
                В данной категории пока нет услуг.
            </p>
            <div class="mt-6">
                <a href="{{ route('services.index') }}" 
                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-900">
                    Посмотреть все услуги
                </a>
            </div>
        </div>
    @endif
</div>
@endsection
