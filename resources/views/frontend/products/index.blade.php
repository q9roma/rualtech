@extends('layouts.frontend')

@section('title', 'Каталог — Алтех')
@section('description', 'Каталог оборудования и позиций прайса. Фильтры по бренду и категории, поиск по названию и артикулу.')

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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1" x-data="{ filtersOpen: false }">
    <form id="catalog-filter" method="get" action="{{ route('products.index') }}" class="lg:flex lg:gap-8 lg:items-start">
        @php
            $inputClass = 'w-full rounded-lg border border-gray-300 bg-white py-2 px-3 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20';
            $selectClass = $inputClass . ' cursor-pointer';
            $labelClass = 'mb-1 block text-xs font-medium text-gray-500';
        @endphp

        <aside class="mb-6 lg:mb-0 lg:w-56 flex-shrink-0 lg:sticky lg:top-24 lg:self-start">
            <button type="button"
                    class="lg:hidden w-full flex items-center justify-between px-3 py-2.5 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-900 shadow-sm hover:bg-gray-50"
                    @click="filtersOpen = !filtersOpen"
                    :aria-expanded="filtersOpen">
                <span>Фильтры</span>
                <svg class="w-5 h-5 text-gray-400 transition-transform shrink-0" :class="{ 'rotate-180': filtersOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </button>

            <div class="mt-3 hidden space-y-3 lg:block lg:mt-0" :class="{ '!block': filtersOpen }">
                <p class="hidden lg:block text-sm font-semibold text-gray-900">Фильтры</p>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 space-y-3">
                    <div>
                        <label for="search" class="{{ $labelClass }}">Поиск</label>
                        <input type="search"
                               name="search"
                               id="search"
                               value="{{ request('search') }}"
                               placeholder="Название, артикул…"
                               autocomplete="off"
                               class="{{ $inputClass }}">
                    </div>
                    <div>
                        <label for="brand" class="{{ $labelClass }}">Бренд</label>
                        <select name="brand" id="brand" class="{{ $selectClass }}" onchange="this.form.requestSubmit()">
                            <option value="">Все</option>
                            @foreach($brands as $b)
                                <option value="{{ $b }}" @selected(request('brand') === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="category" class="{{ $labelClass }}">Категория</label>
                        <select name="category" id="category" class="{{ $selectClass }}" onchange="this.form.requestSubmit()">
                            <option value="">Все</option>
                            @foreach($categories as $c)
                                <option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="sort" class="{{ $labelClass }}">Сортировка</label>
                        <select name="sort" id="sort" class="{{ $selectClass }}" onchange="this.form.requestSubmit()">
                            <option value="name" @selected($sort === 'name')>По названию</option>
                            <option value="brand" @selected($sort === 'brand')>По бренду</option>
                            <option value="price_asc" @selected($sort === 'price_asc')>Цена ↑</option>
                            <option value="price_desc" @selected($sort === 'price_desc')>Цена ↓</option>
                        </select>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <button type="submit"
                                class="inline-flex flex-1 min-w-[5rem] justify-center px-3 py-2 text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition-colors">
                            Найти
                        </button>
                        <a href="{{ route('products.index') }}"
                           class="text-sm font-medium text-gray-600 hover:text-blue-600 py-2 shrink-0">
                            Сбросить
                        </a>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-500 mb-6">
                @if($products->total() > 0)
                    Показано {{ $products->firstItem() }}–{{ $products->lastItem() }} из {{ $products->total() }}
                @else
                    Ничего не найдено
                @endif
            </p>

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
                        Измените фильтры или поиск.
                    </p>
                    <a href="{{ route('products.index') }}" class="mt-4 inline-flex text-sm font-medium text-blue-600 hover:text-blue-800">
                        Сбросить фильтры
                    </a>
                </div>
            @endif
        </div>
    </form>
</div>

<!-- CTA: сразу над футером (блок с flex-1 выше забирает свободную высоту) -->
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
