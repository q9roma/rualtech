@extends('layouts.frontend')

@section('title', 'Блог - IT-новости и статьи | Altech')
@section('description', 'Актуальные новости из мира IT, экспертные статьи о виртуализации, серверных решениях и информационной безопасности.')

@section('content')
<!-- Header Section -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl">
                Блог Altech
            </h1>
            <p class="mt-3 max-w-2xl mx-auto text-xl text-gray-500 sm:mt-4">
                Актуальные IT-новости, экспертные статьи и полезные материалы для бизнеса
            </p>
        </div>
    </div>
</div>

<!-- Featured Posts -->
@if($featuredPosts->count() > 0)
<div class="bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Рекомендуемые статьи</h2>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach($featuredPosts->take(3) as $post)
                <article class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                    @if($post->featured_image)
                        <div class="aspect-w-16 aspect-h-9">
                            <img class="w-full h-48 object-cover" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <time datetime="{{ $post->published_at?->toISOString() }}">
                                {{ $post->formatted_published_at }}
                            </time>
                            <span class="mx-2">•</span>
                            <span>{{ $post->reading_time }} мин чтения</span>
                        </div>
                        
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600">
                                {{ $post->title }}
                            </a>
                        </h3>
                        
                        <p class="text-gray-600 text-sm mb-4">
                            {{ $post->excerpt }}
                        </p>
                        
                        @if($post->tags && is_array($post->tags) && count($post->tags) > 0)
                            <div class="flex flex-wrap gap-1 mb-4">
                                @foreach(array_slice($post->tags, 0, 3) as $tag)
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $tag }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        
                        <a href="{{ route('blog.show', $post->slug) }}" 
                           class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                            Читать полностью
                            <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</div>
@endif

<!-- All Posts -->
<div class="bg-white py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($posts->count() > 0)
            <div class="mb-8">
                <h2 class="text-2xl font-bold text-gray-900">Все статьи</h2>
            </div>
            
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                @foreach($posts as $post)
                    <article class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:shadow-md transition-shadow">
                        @if($post->featured_image)
                            <div class="aspect-w-16 aspect-h-9">
                                <img class="w-full h-48 object-cover" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                            </div>
                        @endif
                        
                        <div class="p-6">
                            <div class="flex items-center text-sm text-gray-500 mb-3">
                                <time datetime="{{ $post->published_at?->toISOString() }}">
                                    {{ $post->formatted_published_at }}
                                </time>
                                <span class="mx-2">•</span>
                                <span>{{ $post->reading_time }} мин чтения</span>
                            </div>
                            
                            <h2 class="text-xl font-semibold text-gray-900 mb-3">
                                <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600">
                                    {{ $post->title }}
                                </a>
                            </h2>
                            
                            <p class="text-gray-600 mb-4">
                                {{ $post->excerpt }}
                            </p>
                            
                            @if($post->tags && is_array($post->tags) && count($post->tags) > 0)
                                <div class="flex flex-wrap gap-1 mb-4">
                                    @foreach(array_slice($post->tags, 0, 4) as $tag)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $tag }}
                                        </span>
                                    @endforeach
                                    @if(count($post->tags) > 4)
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-800">
                                            +{{ count($post->tags) - 4 }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                            
                            <a href="{{ route('blog.show', $post->slug) }}" 
                               class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
                                Читать полностью
                                <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
            
            <!-- Pagination -->
            @if($posts->hasPages())
                <div class="mt-8">
                    {{ $posts->links() }}
                </div>
            @endif
        @else
            <!-- Empty State -->
            <div class="text-center py-12">
                <div class="mx-auto h-12 w-12 text-gray-400">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Нет статей</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Пока что в блоге нет опубликованных статей.
                </p>
            </div>
        @endif
    </div>
</div>

<!-- CTA Section -->
<div class="bg-blue-50 border-t border-blue-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Хотите быть в курсе IT-новостей?
            </h2>
            <p class="text-lg text-gray-600 mb-6">
                Подпишитесь на нашу рассылку и получайте актуальную информацию
            </p>
            <div class="max-w-md mx-auto">
                <form class="flex">
                    <input type="email" placeholder="Ваша электронная почта" 
                           class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" 
                            class="px-6 py-3 bg-blue-600 text-white font-medium rounded-r-lg hover:bg-blue-900 transition-colors">
                        Подписаться
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
