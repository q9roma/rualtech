@extends('layouts.frontend')

@section('title', $post->seo_title ?: $post->title . ' | Блог Altech')
@section('description', $post->seo_description ?: $post->excerpt)

@section('content')
<!-- Breadcrumbs -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
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
                        <a href="{{ route('blog.index') }}" class="ml-4 text-sm font-medium text-gray-500 hover:text-gray-700">
                            Блог
                        </a>
                    </div>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="ml-4 text-sm font-medium text-gray-500">{{ Str::limit($post->title, 30) }}</span>
                    </div>
                </li>
            </ol>
        </nav>
    </div>
</div>

<!-- Article -->
<div class="bg-white">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <article>
            <!-- Article Header -->
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl mb-4">
                    {{ $post->title }}
                </h1>
                
                <div class="flex items-center text-sm text-gray-500 mb-6">
                    <time datetime="{{ $post->published_at?->toISOString() }}">
                        {{ $post->published_at?->format('d.m.Y') }}
                    </time>
                    <span class="mx-2">•</span>
                    <span>{{ $post->reading_time }} мин чтения</span>
                </div>
                
                @if($post->tags && is_array($post->tags) && count($post->tags) > 0)
                    <div class="flex flex-wrap gap-2 mb-6">
                        @foreach($post->tags as $tag)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                {{ $tag }}
                            </span>
                        @endforeach
                    </div>
                @endif
                
                <p class="text-xl text-gray-600 leading-relaxed">
                    {{ $post->excerpt }}
                </p>
            </header>
            
            <!-- Featured Image -->
            @if($post->featured_image)
                <div class="mb-8">
                    <img class="w-full rounded-xl shadow-lg" src="{{ $post->featured_image }}" alt="{{ $post->title }}">
                </div>
            @endif
            
            <!-- Article Content -->
            <div class="prose prose-blue prose-lg max-w-none">
                {!! $post->content !!}
            </div>
            
            <!-- Article Footer -->
            <footer class="mt-8 pt-8 border-t border-gray-200">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">
                        <p>Опубликовано {{ $post->published_at?->format('d.m.Y в H:i') }}</p>
                    </div>
                    
                    <!-- Share Buttons -->
                    <div class="flex items-center space-x-3">
                        <span class="text-sm text-gray-500">Поделиться:</span>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 4.557c-.883.392-1.832.656-2.828.775 1.017-.609 1.798-1.574 2.165-2.724-.951.564-2.005.974-3.127 1.195-.897-.957-2.178-1.555-3.594-1.555-3.179 0-5.515 2.966-4.797 6.045-4.091-.205-7.719-2.165-10.148-5.144-1.29 2.213-.669 5.108 1.523 6.574-.806-.026-1.566-.247-2.229-.616-.054 2.281 1.581 4.415 3.949 4.89-.693.188-1.452.232-2.224.084.626 1.956 2.444 3.379 4.6 3.419-2.07 1.623-4.678 2.348-7.29 2.04 2.179 1.397 4.768 2.212 7.548 2.212 9.142 0 14.307-7.721 13.995-14.646.962-.695 1.797-1.562 2.457-2.549z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M22.46 6c-.77.35-1.6.58-2.46.69.88-.53 1.56-1.37 1.88-2.38-.83.5-1.75.85-2.72 1.05C18.37 4.5 17.26 4 16 4c-2.35 0-4.27 1.92-4.27 4.29 0 .34.04.67.11.98C8.28 9.09 5.11 7.38 3 4.79c-.37.63-.58 1.37-.58 2.15 0 1.49.75 2.81 1.91 3.56-.71 0-1.37-.2-1.95-.5v.03c0 2.08 1.48 3.82 3.44 4.21a4.22 4.22 0 0 1-1.93.07 4.28 4.28 0 0 0 4 2.98 8.521 8.521 0 0 1-5.33 1.84c-.34 0-.68-.02-1.02-.06C3.44 20.29 5.7 21 8.12 21 16 21 20.33 14.46 20.33 8.79c0-.19 0-.37-.01-.56.84-.6 1.56-1.36 2.14-2.23z"/>
                            </svg>
                        </a>
                        <a href="#" class="text-gray-400 hover:text-blue-600 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </footer>
        </article>
    </div>
</div>

<!-- Related Posts -->
@if($relatedPosts->count() > 0)
<div class="bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-2xl font-bold text-gray-900 mb-6">Читайте также</h2>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach($relatedPosts as $relatedPost)
                <article class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow">
                    @if($relatedPost->featured_image)
                        <div class="aspect-w-16 aspect-h-9">
                            <img class="w-full h-48 object-cover" src="{{ $relatedPost->featured_image }}" alt="{{ $relatedPost->title }}">
                        </div>
                    @endif
                    
                    <div class="p-6">
                        <div class="flex items-center text-sm text-gray-500 mb-3">
                            <time datetime="{{ $relatedPost->published_at?->toISOString() }}">
                                {{ $relatedPost->formatted_published_at }}
                            </time>
                            <span class="mx-2">•</span>
                            <span>{{ $relatedPost->reading_time }} мин чтения</span>
                        </div>
                        
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            <a href="{{ route('blog.show', $relatedPost->slug) }}" class="hover:text-blue-600">
                                {{ $relatedPost->title }}
                            </a>
                        </h3>
                        
                        <p class="text-gray-600 text-sm mb-4">
                            {{ Str::limit($relatedPost->excerpt, 100) }}
                        </p>
                        
                        <a href="{{ route('blog.show', $relatedPost->slug) }}" 
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
        
        <div class="text-center mt-8">
            <a href="{{ route('blog.index') }}" 
               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                Все статьи блога
            </a>
        </div>
    </div>
</div>
@endif
@endsection
