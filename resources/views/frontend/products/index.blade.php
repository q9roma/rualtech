@extends('layouts.frontend')

@section('title', 'Каталог — Алтех')
@section('description', 'Каталог оборудования и позиций прайса. Категории, поиск по названию и артикулу.')

@section('content')
<div class="flex flex-1 flex-col min-h-0">
<!-- Header Section (как на /services) -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="md:flex md:items-center md:justify-between">
            <div class="min-w-0 flex-1">
                <nav class="flex text-sm text-gray-500 mb-3" aria-label="Навигация">
                    <a href="{{ route('home') }}" class="hover:text-gray-700">Главная</a>
                    <span class="mx-2 text-gray-300">/</span>
                    <span class="text-gray-900 font-medium">Каталог</span>
                </nav>
                <h1 class="text-2xl font-bold leading-7 text-gray-900 sm:text-3xl">
                    Каталог прайса
                </h1>
                <p class="mt-1 text-sm text-gray-500">
                    Позиции из актуального прайса. Цены ориентировочные — уточняйте по запросу.
                </p>
            </div>
        </div>
    </div>
</div>

@php
    $inputClass = 'rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
    $selectClass = $inputClass . ' cursor-pointer appearance-none pr-9';
    $currentCategory = request('category');
    $queryBase = request()->only(['search', 'sort']);
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1" x-data="{ categoriesOpen: false }">
    <div class="lg:flex lg:gap-10 lg:items-start">
        {{-- Категории (как на neo-alpha: список + количество) --}}
        <aside class="mb-6 lg:mb-0 lg:w-72 flex-shrink-0 lg:sticky lg:top-24 lg:self-start">
            <button type="button"
                    class="lg:hidden w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50"
                    @click="categoriesOpen = !categoriesOpen"
                    :aria-expanded="categoriesOpen">
                <span>Категории</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0" :class="{ 'rotate-180': categoriesOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <nav class="mt-3 hidden lg:block lg:mt-0 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5" :class="{ '!block': categoriesOpen }" aria-label="Категории каталога">
                <h2 class="text-sm font-semibold text-gray-900 mb-3">Категории</h2>
                <ul class="space-y-0.5 text-sm">
                    <li>
                        <a href="{{ route('products.index', $queryBase) }}"
                           class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition-colors {{ $currentCategory ? 'text-gray-700 hover:bg-gray-50' : 'bg-blue-50 text-blue-900 font-medium ring-1 ring-blue-100' }}">
                            <span>Все категории</span>
                            <span class="tabular-nums text-gray-500 {{ $currentCategory ? '' : 'text-blue-700' }}">{{ $totalActiveProducts }}</span>
                        </a>
                    </li>
                    @foreach($categoryDirectory as $row)
                        @php
                            $isActive = $currentCategory === $row->category;
                            $catParams = array_merge($queryBase, ['category' => $row->category]);
                        @endphp
                        <li>
                            <a href="{{ route('products.index', $catParams) }}"
                               class="flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition-colors {{ $isActive ? 'bg-blue-50 text-blue-900 font-medium ring-1 ring-blue-100' : 'text-gray-700 hover:bg-gray-50' }}">
                                <span class="min-w-0 break-words">{{ $row->category }}</span>
                                <span class="shrink-0 tabular-nums {{ $isActive ? 'text-blue-700' : 'text-gray-500' }}">{{ $row->products_count }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                @if($categoryDirectory->isEmpty())
                    <p class="text-xs text-gray-500 mt-2">Категории появятся после импорта прайса с заполненным полем «Категория».</p>
                @endif
            </nav>
        </aside>

        <div class="flex-1 min-w-0">
            <form method="get" action="{{ route('products.index') }}" class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                @if($currentCategory)
                    <input type="hidden" name="category" value="{{ $currentCategory }}">
                @endif
                <div class="w-full min-w-0 flex-1 sm:min-w-[12rem]">
                    <label for="catalog-search" class="sr-only">Поиск</label>
                    <input type="search"
                           name="search"
                           id="catalog-search"
                           value="{{ request('search') }}"
                           placeholder="Поиск по названию, артикулу…"
                           autocomplete="off"
                           class="w-full {{ $inputClass }}">
                </div>
                <div class="flex w-full min-w-0 items-center gap-2 sm:w-auto sm:items-end">
                    <div class="relative min-w-0 flex-1 sm:min-w-[11rem] sm:flex-none sm:w-auto">
                        <label for="catalog-sort" class="sr-only">Сортировка</label>
                        <select name="sort" id="catalog-sort" class="w-full {{ $selectClass }}" onchange="this.form.requestSubmit()">
                            <option value="name" @selected($sort === 'name')>По названию</option>
                            <option value="brand" @selected($sort === 'brand')>По бренду</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Цена ↑</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Цена ↓</option>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-500" aria-hidden="true">
                            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button type="submit"
                                class="inline-flex justify-center whitespace-nowrap px-4 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            Найти
                        </button>
                        @if(request()->filled('search') || request()->filled('category') || ($sort !== 'name'))
                            <a href="{{ route('products.index') }}"
                               class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-600 hover:text-blue-600">
                                Сбросить
                            </a>
                        @endif
                    </div>
                </div>
            </form>

            @if($products->count() > 0)
                <ul class="grid grid-cols-1 gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach($products as $product)
                        <li>
                            <a href="{{ route('products.show', $product) }}"
                               class="group block h-full bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl hover:shadow-md transition-shadow p-6 flex flex-col">
                                <h2 class="text-base font-semibold text-gray-900 leading-snug line-clamp-3 group-hover:text-blue-600 transition-colors">
                                    {{ $product->name }}
                                </h2>
                                @if($product->sku)
                                    <p class="mt-2 text-xs text-gray-500 font-mono">{{ $product->sku }}</p>
                                @endif
                                @if($product->brand)
                                    <p class="mt-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                            {{ $product->brand }}
                                        </span>
                                    </p>
                                @endif
                                <p class="mt-auto pt-4">
                                    <span class="text-lg font-semibold text-blue-600">
                                        {{ $product->formatted_price }}
                                    </span>
                                </p>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-8">
                    {{ $products->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <div class="mx-auto h-12 w-12 text-gray-400">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">Позиции не найдены</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        Измените поиск или выберите другую категорию.
                    </p>
                    <a href="{{ route('products.index') }}" class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-800">
                        Весь каталог
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- CTA: сразу над футером -->
<div class="shrink-0 bg-blue-50 border-t border-blue-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">
                Нужна поставка или КП?
            </h2>
            <p class="text-lg text-gray-600 mb-6">
                Оставьте заявку — подберём конфигурацию и условия.
            </p>
            <button type="button" onclick="openContactForm()"
                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-900 transition-colors">
                Получить консультацию
            </button>
        </div>
    </div>
</div>
</div>
@endsection
