@extends('layouts.frontend')

@section('title', $product->name . ' — Каталог | Алтех')
@section('description', Str::limit(strip_tags($product->name . ($product->sku ? ' Артикул ' . $product->sku : '')), 160))

@php
    // Заглушка: «сколько смотрят» — стабильно для позиции, выглядит по-разному у разных товаров
    $viewersCount = 3 + (abs(crc32((string) $product->getKey() . '|' . ($product->slug ?? ''))) % 18);
    $viewersWord = match (true) {
        $viewersCount % 10 === 1 && $viewersCount % 100 !== 11 => 'человек',
        in_array($viewersCount % 10, [2, 3, 4], true) && ! in_array($viewersCount % 100, [12, 13, 14], true) => 'человека',
        default => 'человек',
    };
@endphp

@section('content')
<div class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <nav class="flex flex-wrap items-center gap-x-1 text-sm text-gray-500" aria-label="Навигация">
            <a href="{{ route('home') }}" class="hover:text-gray-700">Главная</a>
            <span class="text-gray-300 mx-1">/</span>
            <a href="{{ route('products.index') }}" class="hover:text-gray-700">Каталог</a>
            <span class="text-gray-300 mx-1">/</span>
            <span class="text-gray-900 font-medium line-clamp-2">{{ $product->name }}</span>
        </nav>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="lg:grid lg:grid-cols-12 lg:gap-8 lg:items-start">
        {{-- Основная информация --}}
        <div class="lg:col-span-7 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl">
                {{-- Заглушка обложки (фото товара) --}}
                <div class="relative aspect-[4/3] sm:aspect-[16/10] bg-gradient-to-br from-slate-900 via-slate-800 to-blue-900 flex items-center justify-center overflow-hidden">
                    <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_30%_20%,rgba(255,255,255,0.12),transparent_55%)]" aria-hidden="true"></div>
                    <div class="relative flex items-center justify-center px-8">
                        <img src="{{ asset('images/logo_light.svg') }}"
                             alt=""
                             class="h-14 sm:h-20 w-auto opacity-95 drop-shadow-lg"
                             width="200"
                             height="80">
                    </div>
                </div>

                <div class="px-6 py-5 sm:px-8 sm:py-6 border-b border-gray-100">
                    <h1 class="text-xl sm:text-2xl font-bold text-gray-900 leading-snug">
                        {{ $product->name }}
                    </h1>
                    <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2">
                        @if($product->availability)
                            @php
                                $avRaw = trim((string) $product->availability);
                                $avLow = mb_strtolower($avRaw);
                                $isSpecial = str_contains($avLow, 'заказ')
                                    || str_contains($avLow, 'нет')
                                    || str_contains($avLow, 'ожида')
                                    || str_contains($avLow, 'транзит');
                                $tail = preg_replace('/^\s*в\s+наличии\s*/iu', '', $avRaw);
                                $availabilityPhrase = $isSpecial
                                    ? $avRaw
                                    : ('в наличии ' . ($tail !== '' ? $tail : $avRaw));
                            @endphp
                            <span class="inline-flex max-w-full items-center rounded-md px-2.5 py-1 text-sm font-medium {{ $isSpecial ? 'bg-gray-100 text-gray-800 ring-1 ring-gray-200' : 'bg-green-100 text-green-800 ring-1 ring-green-600/15' }}">
                                Наличие: {{ $availabilityPhrase }}
                            </span>
                        @endif
                        <span class="text-sm text-gray-500">
                            Прямо сейчас смотрят <span class="font-medium tabular-nums text-gray-700">{{ $viewersCount }}</span> {{ $viewersWord }}
                        </span>
                    </div>
                </div>

                <div class="px-6 py-5 sm:px-8 sm:py-6">
                    <h2 class="sr-only">Параметры позиции</h2>
                    <dl class="divide-y divide-gray-100 text-sm">
                        @if($product->sku)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 py-3 first:pt-0 sm:gap-4 sm:items-start">
                                <dt class="font-medium text-gray-500 sm:col-span-1">Артикул</dt>
                                <dd class="text-gray-900 sm:col-span-2">
                                    <code class="text-sm font-mono bg-gray-50 px-2 py-1 rounded border border-gray-100">{{ $product->sku }}</code>
                                </dd>
                            </div>
                        @endif
                        @if($product->brand)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 py-3 sm:gap-4 sm:items-start">
                                <dt class="font-medium text-gray-500 sm:col-span-1">Бренд</dt>
                                <dd class="text-gray-900 sm:col-span-2">{{ $product->brand }}</dd>
                            </div>
                        @endif
                        @if($product->category)
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1 py-3 sm:gap-4 sm:items-start">
                                <dt class="font-medium text-gray-500 sm:col-span-1">Категория</dt>
                                <dd class="text-gray-900 sm:col-span-2">{{ $product->category }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        {{-- Цена и действия: заметный блок, на десктопе липнет при прокрутке --}}
        <div class="lg:col-span-5 mt-6 lg:mt-0 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl lg:sticky lg:top-24">
                <div class="px-6 py-5 sm:px-8 sm:py-6 bg-gray-50/80 border-b border-gray-100">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Стоимость</p>
                    <p class="mt-2 text-2xl sm:text-3xl font-bold text-blue-600 tabular-nums">
                        {{ $product->formatted_price }}
                    </p>
                    <p class="mt-2 text-xs text-gray-500 leading-relaxed">
                        Ориентировочная цена по прайсу. Итоговая стоимость и сроки — по запросу.
                    </p>
                </div>
                <div class="px-6 py-5 sm:px-8 sm:py-6 space-y-3">
                    <button type="button" onclick="openContactForm()"
                            class="w-full inline-flex justify-center items-center px-5 py-3 border border-transparent rounded-md text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Запросить КП или поставку
                    </button>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm ring-1 ring-gray-900/5 rounded-xl">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Похожие позиции</h2>
                    <p class="mt-0.5 text-xs text-gray-500">Та же категория или бренд</p>
                </div>
                <div class="px-4 py-3">
                    @if($related->isEmpty())
                        <p class="text-sm text-gray-500 px-2 py-4 text-center">Других позиций пока нет.</p>
                    @else
                        <ul class="divide-y divide-gray-100">
                            @foreach($related as $item)
                                <li>
                                    <a href="{{ route('products.show', $item) }}"
                                       class="group flex gap-3 py-3 px-2 -mx-2 rounded-lg hover:bg-gray-50 transition-colors">
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-900 group-hover:text-blue-600 line-clamp-2 leading-snug">
                                                {{ $item->name }}
                                            </p>
                                            @if($item->sku)
                                                <p class="mt-1 text-xs text-gray-500 font-mono truncate">{{ $item->sku }}</p>
                                            @endif
                                        </div>
                                        <div class="flex-shrink-0 self-center text-right">
                                            <span class="text-sm font-semibold text-blue-600 tabular-nums whitespace-nowrap">
                                                {{ $item->formatted_price }}
                                            </span>
                                        </div>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
