@php($props = $page['props'])
<main class="max-w-5xl mx-auto p-6">
    <nav aria-label="Основная навигация">
        @foreach(['home' => 'Главная', 'catalog' => 'Каталог', 'services' => 'Услуги', 'blog' => 'Блог'] as $key => $label)
            <a href="{{ $props['navigation'][$key] }}">{{ $label }}</a>
        @endforeach
    </nav>
    <h1>{{ $props['title'] ?? 'Алтех' }}</h1>
    @if(!empty($props['description']))<p>{{ $props['description'] }}</p>@endif
    @if(!empty($props['content']))<div>{!! $props['content'] !!}</div>@endif
    @if(!empty($props['service']['price']))<p>{{ $props['service']['price'] }}</p>@endif
    @if(!empty($props['features']))
        <ul>@foreach($props['features'] as $feature)<li>{{ is_string($feature) ? $feature : '' }}</li>@endforeach</ul>
    @endif
    @foreach(['directions', 'categories', 'catalogProducts', 'services', 'posts', 'products', 'pages', 'related', 'variants'] as $key)
        @php($listing = $props[$key] ?? [])
        @php($items = $listing['data'] ?? $listing)
        @foreach($items as $item)
            @php($item = is_object($item) ? (array) $item : $item)
            @if(is_array($item))
                <section>
                    @if(!empty($item['url']))<h2><a href="{{ $item['url'] }}">{{ $item['name'] ?? $item['title'] ?? $item['category'] ?? '' }}</a></h2>@endif
                    @if(!empty($item['description']))<p>{{ $item['description'] }}</p>@endif
                    @if(!empty($item['formattedPrice']))<p>{{ $item['formattedPrice'] }}</p>@endif
                    @foreach($item['services'] ?? $item['children'] ?? [] as $child)
                        <a href="{{ $child['url'] }}">{{ $child['name'] }}</a>
                    @endforeach
                </section>
            @endif
        @endforeach
        @if(!empty($listing['prev_page_url']))<a href="{{ $listing['prev_page_url'] }}">Предыдущая страница</a>@endif
        @if(!empty($listing['next_page_url']))<a href="{{ $listing['next_page_url'] }}">Следующая страница</a>@endif
    @endforeach
    @foreach($props['sections'] ?? [] as $section)
        @if(!empty($props['selectedCollection']) && $props['selectedCollection'] !== $section['key']) @continue @endif
        @if($section['total'] === 0) @continue @endif
        <section>
            <h2><a href="{{ $section['url'] }}">{{ $section['title'] }}</a></h2>
            <p>{{ $section['text'] }}</p>
            @foreach($section['products'] as $product)
                <article><h3><a href="{{ $product['url'] }}">{{ $product['name'] }}</a></h3><p>{{ $product['formattedPrice'] }}</p></article>
            @endforeach
            @if(!empty($section['pagination']['prev_page_url']))<a href="{{ $section['pagination']['prev_page_url'] }}">Предыдущая страница</a>@endif
            @if(!empty($section['pagination']['next_page_url']))<a href="{{ $section['pagination']['next_page_url'] }}">Следующая страница</a>@endif
        </section>
    @endforeach
    @if(!empty($props['brief']))<h2>Подберём под вашу задачу</h2><p>{{ $props['brief'] }}</p>@endif
    @if(!empty($props['categoryGuide']))
        <section><h2>{{ $props['categoryGuide']['heading'] }}</h2><p>{{ $props['categoryGuide']['intro'] }}</p>
            <ul>@foreach($props['categoryGuide']['checks'] as $check)<li>{{ $check }}</li>@endforeach</ul>
        </section>
    @endif
    @if($page['component'] === 'delivery')
        <h2>Способы доставки</h2><p>Доставим оборудование нашей курьерской службой или транспортной компанией до терминала либо по адресу.</p>
        <h2>Стоимость и сроки</h2><p>По Москве и Московской области — в течение суток. При заказе от 150 000 ₽ доставка за наш счёт.</p>
    @endif
</main>
