<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ServiceDirections
{
    public const ALIASES = [
        'servernoe-oborudovanie' => 'servers-storage',
        'sistemy-xraneniia-dannyx-sxd' => 'servers-storage',
        'setevoe-oborudovanie' => 'networks',
        'telefoniia' => 'meeting-rooms',
        'personalnye-rabocie-reseniia' => 'workplaces',
        'programmnoe-obespecenie' => 'software',
        'orgtexnika' => 'printing',
        'komplektuiushhie-i-komponenty' => 'pc-upgrade',
    ];

    public static function cards(): array
    {
        return collect(config('directions'))->map(fn ($direction, $slug) => [
            'slug' => $slug, 'title' => $direction['title'], 'icon' => $direction['icon'],
            'description' => $direction['description'], 'url' => route('services.category', $slug),
        ])->values()->all();
    }

    public static function query(array $group): Builder
    {
        $categories = $group['categories'];
        $query = Product::active()->where(function (Builder $q) use ($categories) {
            // A classified product must use its assigned category, not stale supplier text.
            $q->whereHas('catalogCategory', fn ($c) => $c->where('is_active', true)->whereIn('name', $categories))
                ->orWhere(fn ($legacy) => $legacy->whereNull('catalog_category_id')->whereIn('category', $categories));
        });
        if ($group['include']) {
            $query->where(function ($q) use ($group) {
                foreach ($group['include'] as $pattern) $q->orWhere('name', 'like', $pattern);
            });
        }
        foreach ($group['exclude'] as $pattern) $query->where('name', 'not like', $pattern);
        return $query->orderBy('name')->orderBy('id');
    }

    /** Represent each product type before adding more items of the same type. */
    public static function preview(array $group): \Illuminate\Support\Collection
    {
        if (count($group['categories']) === 1) return self::query($group)->limit(4)->get();
        $buckets = collect($group['categories'])->map(fn ($category) => self::query([
            ...$group, 'categories' => [$category],
        ])->limit(4)->get());
        $products = collect();
        for ($round = 0; $round < 4 && $products->count() < 4; $round++) {
            foreach ($buckets as $bucket) {
                if (isset($bucket[$round]) && ! $products->contains('id', $bucket[$round]->id)) $products->push($bucket[$round]);
                if ($products->count() === 4) break;
            }
        }
        return $products;
    }

    public static function exampleBundle(): ?array
    {
        $parts = [
            ['sku' => 'DS423', 'label' => 'Synology DS423', 'quantity' => 1],
            ['sku' => 'ST4000NT001', 'label' => 'IronWolf Pro 4 ТБ', 'quantity' => 2],
            ['sku' => 'BR700ELCD', 'label' => 'CyberPower BR700ELCD', 'quantity' => 1],
        ];
        $products = Product::active()->whereIn('sku', array_column($parts, 'sku'))->get()->keyBy('sku');
        $items = [];
        $total = 0;
        $priced = true;
        foreach ($parts as $part) {
            $product = $products->get($part['sku']);
            if (! $product) return null;
            $priced = $priced && (float) $product->price > 0 && $product->currency === 'RUB';
            $total += (int) round((float) $product->price * 100) * $part['quantity'];
            $items[] = [...$part, 'product' => Storefront::product($product)];
        }
        return ['items' => $items, 'formattedTotal' => $priced ? number_format($total / 100, 2, ',', ' ').' ₽' : null];
    }

    public static function page(string $slug): array
    {
        $direction = config('directions.'.$slug);
        abort_unless($direction, 404);
        request()->validate(['collection' => ['nullable', 'string', 'max:60'], 'page' => ['nullable', 'integer', 'min:1']]);
        $selected = request()->string('collection')->value();
        $groups = collect($direction['groups']);
        abort_if($selected !== '' && ! $groups->contains('key', $selected), 404);
        $url = route('services.category', $slug);
        $sections = $groups->map(function ($group) use ($slug, $selected) {
            $query = self::query($group);
            $total = (clone $query)->count();
            $pagination = null;
            if ($selected === $group['key']) {
                $pagination = $query->paginate(24)->withQueryString();
                abort_if($pagination->currentPage() > $pagination->lastPage(), 404);
                $products = $pagination->getCollection();
            } else {
                $products = $selected === '' ? self::preview($group) : collect();
            }
            return [
                'key' => $group['key'], 'title' => $group['title'], 'text' => $group['text'],
                'total' => $total, 'products' => $products->map(Storefront::cartProduct(...))->values(),
                'url' => route('services.category', ['categorySlug' => $slug, 'collection' => $group['key']]),
                'pagination' => $pagination ? [
                    'current_page' => $pagination->currentPage(), 'last_page' => $pagination->lastPage(),
                    'prev_page_url' => $pagination->previousPageUrl(), 'next_page_url' => $pagination->nextPageUrl(),
                ] : null,
            ];
        });
        $collectionTitle = $selected ? $groups->firstWhere('key', $selected)['title'] : null;
        $page = max(1, (int) request('page', 1));
        return [
            'title' => $direction['title'], 'description' => $selected === '' ? ($direction['seo_description'] ?? $direction['description']) : $direction['description'],
            'seoTitle' => ($collectionTitle ? $collectionTitle.' — '.$direction['title'] : ($direction['seo_title'] ?? $direction['title'])).($page > 1 ? ' — страница '.$page : ''),
            'brief' => $direction['brief'], 'directionUrl' => $url, 'directionKey' => $slug,
            'canonicalUrl' => $selected ? route('services.category', array_filter(['categorySlug' => $slug, 'collection' => $selected, 'page' => $page > 1 ? $page : null])) : $url,
            'selectedCollection' => $selected, 'sections' => $sections,
            'exampleBundle' => $slug === 'servers-storage' && $selected === '' ? self::exampleBundle() : null,
            'directions' => collect(self::cards())->where('slug', '!=', $slug)->values(),
        ];
    }
}
