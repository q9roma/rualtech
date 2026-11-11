<article class="max-w-5xl mx-auto p-6">
    <nav aria-label="Навигация"><a href="{{ route('home') }}">Главная</a> / <a href="{{ route('products.index') }}">Каталог</a></nav>
    <h1>{{ $item['name'] }}</h1>
    @foreach($item['images'] ?? [] as $image)
        <img src="{{ $image }}" alt="{{ $item['name'] }}" width="400" loading="lazy">
    @endforeach
    <p>{{ $item['formattedPrice'] }}</p>
    @if($item['availability'])<p>Наличие: {{ $item['availability'] }}</p>@endif
    <h2>Характеристики</h2>
    <dl>
        @foreach(['brand' => 'Производитель', 'sku' => 'Артикул', 'category' => 'Категория'] as $key => $label)
            @if($item[$key])<dt>{{ $label }}</dt><dd>{{ $item[$key] }}</dd>@endif
        @endforeach
        @foreach($item['specifications'] ?? [] as $spec)
            <dt>{{ $spec['name'] }}</dt><dd>{{ $spec['value'] }}</dd>
        @endforeach
    </dl>
    @if($description = (($item['description'] ?? '') ?: ($item['supplierDescription'] ?? '')))
        <h2>Описание</h2><p style="white-space: pre-line">{{ $description }}</p>
    @endif
    <a href="{{ route('products.index') }}">Все товары</a>
</article>
