@extends('la                    <a href="{{ route('home') }}" class="text-gray-500 hover:text-blue-600">outs.frontend')

@section('title', $page->seo_title ?: $page->title)
@section('description', $page->seo_description ?: Str::limit(strip_tags($page->content), 160))

@section('content')
<div class="bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumbs -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" class="text-gray-500 hover:text-blue-600">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Главная
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-500 ml-1 md:ml-2">{{ $page->title }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <!-- Page Content -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="p-8">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-6">
                    {{ $page->title }}
                </h1>
                
                <div class="prose prose-lg max-w-none">
                    {!! $page->content !!}
                </div>
                
                <!-- Page meta -->
                <div class="mt-8 pt-8 border-t border-gray-200">
                    <div class="flex items-center text-sm text-gray-500">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Обновлено: {{ $page->updated_at->format('d.m.Y в H:i') }}
                    </div>
                </div>

                <!-- Back to home -->
                <div class="mt-8">
                    <a href="{{ route('home') }}" 
                       class="inline-flex items-center text-blue-600 hover:text-blue-800 font-medium">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Вернуться на главную
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
