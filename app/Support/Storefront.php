<?php

namespace App\Support;

use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** Explicit public DTOs; never serialize administration fields to the storefront. */
class Storefront
{
    public static function html(?string $html): string
    {
        return (new HtmlSanitizer((new HtmlSanitizerConfig())->allowSafeElements()
            ->allowRelativeLinks()->allowRelativeMedias()))->sanitize($html ?? '');
    }

    public static function category($category): array
    {
        return ['id' => $category->id, 'name' => $category->name,
            'description' => strip_tags($category->description ?? ''),
            'url' => route('services.category', $category->slug)];
    }

    public static function service($service): array
    {
        return ['id' => $service->id, 'name' => $service->name,
            'description' => strip_tags($service->short_description ?? ''),
            'price' => $service->formatted_price,
            'url' => route('services.show', [$service->category->slug, $service->slug])];
    }

    public static function post($post): array
    {
        return ['id' => $post->id, 'name' => $post->title,
            'description' => strip_tags($post->excerpt ?? ''),
            'date' => $post->formatted_published_at,
            'image' => self::image($post->featured_image),
            'url' => route('blog.show', $post->slug)];
    }

    private static function image(?string $path): ?string
    {
        if (! $path) return null;
        if (preg_match('~^https?://~i', $path)) return $path;
        if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) return $path;
        if (str_contains($path, ':') || str_starts_with($path, '//')) return null;
        return asset('storage/'.ltrim($path, '/'));
    }

    public static function product($product): array
    {
        $images = $product->photos !== null
            ? array_map(fn ($index) => route('products.photo', [$product->id, $index]), array_keys($product->photos))
            : (ProductMediaQuality::images($product) ?? ProductMediaPilot::images($product));
        $previews = ProductImageDelivery::previews($images);
        return ['id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
            'brand' => $product->brand, 'category' => $product->catalogCategory?->name ?? $product->category,
            'categoryUrl' => $product->catalogCategory?->is_active && (!$product->catalogCategory->parent_id || $product->catalogCategory->parent?->is_active) ? route('catalog.category', $product->catalogCategory->slug) : route('products.index', ['category' => $product->category]),
            'categoryTone' => CatalogDirectory::categoryTone($product->category),
            'images' => $images,
            'imagePreviews' => $previews,
            'imageBackgrounds' => ProductImageDelivery::backgrounds($previews),
            'hasPrice' => $product->price !== null && (float) $product->price > 0,
            'inStock' => \App\Commerce\ProductBridge::inStock($product),
            'availability' => $product->availability, 'formattedPrice' => $product->formatted_price,
            'url' => route('products.show', $product)];
    }

    public static function cartProduct($product): array
    {
        return [
            ...self::product($product),
            'canAddToCart' => config('commerce.enabled') && \App\Commerce\ProductBridge::eligible($product),
            'addToCartUrl' => route('cart.add', $product),
        ];
    }

    public static function supplierDescription($product): ?string
    {
        $raw = \Illuminate\Support\Facades\DB::table('catalog_source_products')
            ->where('product_id', $product->id)->where('source', 'supplier-price-32')
            ->orderByDesc('updated_at')->orderByDesc('id')->value('supplier_data');
        $description = (json_decode($raw ?? '{}', true) ?? [])['Описание'] ?? null;
        return is_string($description) && trim($description) !== '' ? trim($description) : null;
    }

    public static function page(string $view, array $data): \Illuminate\View\View|\Inertia\Response
    {
        if (! config('storefront.inertia')) return view($view, $data);

        $props = match ($view) {
            'frontend.home' => [
                'directions' => ServiceDirections::cards(),
                'title' => 'ИТ-решения для бизнеса',
                'description' => 'Оборудование и программное обеспечение для бизнеса: рабочие места, серверы, сети, видеосвязь, печать и бесперебойное питание. Подбор под вашу задачу.',
                'categories' => $data['serviceCategories']->map(self::category(...)),
                'catalogProducts' => $data['catalogProducts']->map(self::cartProduct(...)),
                'catalogSections' => $data['catalogSections'],
                'catalogFilter' => $data['catalogFilter'],
                'catalogFilters' => $data['catalogFilters'],
                'catalogTotal' => $data['catalogTotal'],
                'services' => $data['featuredServices']->filter(fn ($s) => $s->category?->is_active)->values()->map(self::service(...)),
                'pages' => $data['pages']->map(fn ($p) => ['id' => $p->id, 'name' => $p->title, 'url' => route('pages.show', $p->slug)]),
            ],
            'frontend.services.index' => [
                'title' => 'Услуги', 'categories' => $data['categories']->map(fn ($c) => [
                    ...self::category($c), 'services' => $c->activeServices->map(self::service(...)),
                ]),
            ],
            'frontend.services.category' => [
                'title' => $data['category']->name, 'seoTitle' => $data['category']->seo_title,
                'description' => $data['category']->seo_description ?: strip_tags($data['category']->description ?? ''),
                'services' => $data['services']->through(self::service(...)),
            ],
            'frontend.services.show' => [
                'title' => $data['service']->name, 'seoTitle' => $data['service']->seo_title,
                'description' => $data['service']->seo_description ?: $data['service']->short_description,
                'content' => self::html($data['service']->description),
                'service' => self::service($data['service']),
                'features' => $data['service']->features ?? [],
                'category' => self::category($data['category']),
                'related' => $data['relatedServices']->map(self::service(...)),
            ],
            'frontend.blog.index' => [
                'title' => 'Блог', 'posts' => $data['posts']->through(self::post(...)),
                'featured' => $data['featuredPosts']->map(self::post(...)),
            ],
            'frontend.blog.show' => [
                'title' => $data['post']->title, 'seoTitle' => $data['post']->seo_title,
                'description' => $data['post']->seo_description ?: $data['post']->excerpt,
                'post' => self::post($data['post']), 'content' => self::html($data['post']->content),
                'tags' => $data['post']->tags ?? [], 'readingTime' => $data['post']->reading_time,
                'related' => $data['relatedPosts']->map(self::post(...)),
            ],
            'frontend.page' => [
                'title' => $data['page']->title, 'seoTitle' => $data['page']->seo_title,
                'description' => $data['page']->seo_description ?: Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags(str_replace('><', '> <', $data['page']->content)))), 160),
                'content' => self::html($data['page']->content),
                'updatedAt' => $data['page']->updated_at?->format('d.m.Y'),
            ],
            'frontend.products.show' => [
                ...ProductSeo::metadata($data['product']), 'product' => [...self::cartProduct($data['product']), 'description' => $data['product']->description, 'specifications' => $data['product']->specifications ?? [], 'supplierDescription' => self::supplierDescription($data['product'])],
                'related' => $data['related']->map(self::cartProduct(...)),
                'variants' => $data['variants']->map(self::cartProduct(...)),
                'canAddToCart' => config('commerce.enabled') && \App\Commerce\ProductBridge::eligible($data['product']),
                'addToCartUrl' => route('cart.add', $data['product']),
            ],
        };

        $pageNumber = max(1, (int) request('page', 1));
        if ($pageNumber > 1 && in_array($view, ['frontend.blog.index', 'frontend.services.category'])) {
            $props['seoTitle'] = ($props['seoTitle'] ?? $props['title']).' — страница '.$pageNumber;
        }
        if (empty($props['description'])) {
            $props['description'] = match ($view) {
                'frontend.services.index' => 'ИТ-услуги Алтех: подбор оборудования, интеграция и техническая поддержка бизнеса.',
                'frontend.blog.index' => 'Блог Алтех: статьи об ИТ-оборудовании, технологиях и решениях для бизнеса.',
                default => $props['title'].' — информация, решения и услуги Алтех.',
            };
        }
        if ($pageNumber > 1) $props['description'] .= ' Страница '.$pageNumber.'.';
        return Inertia::render(str_replace('.', '/', substr($view, 9)), [
            ...$props, 'canonicalUrl' => $data['canonicalUrl'] ?? request()->url(),
        ]);
    }
}
