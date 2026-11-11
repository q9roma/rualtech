<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'description', 'specifications', 'photos',
        'category',
        'brand',
        'sku',
        'name',
        'slug',
        'availability',
        'price',
        'currency',
        'is_active',
        'catalog_category_id',
        'source_category',
        'stock_free',
        'stock_transit',
    ];

    protected $casts = [
        'specifications' => 'array',
        'photos' => 'array',
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(fn (Product $product) => \App\Admin\CatalogHistory::modelSaved($product, true));
        static::updated(fn (Product $product) => \App\Admin\CatalogHistory::modelSaved($product));
        static::saving(function (Product $product): void {
            $slug = trim((string) ($product->slug ?? ''));
            if ($slug === '') {
                $product->slug = $product->freshUniqueSlug();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        // Числовой id в URL и в Filament; человекочитаемый slug хранится в БД для SEO/редиректов при необходимости.
        return 'id';
    }

    /** Each word may match a different public field; SQL wildcards are literal. */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        foreach (preg_split('/\s+/u', trim($search), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $word).'%';
            $query->where(function (Builder $q) use ($pattern) {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("sku LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("brand LIKE ? ESCAPE '!'", [$pattern]);
            });
        }
        return $query;
    }

    public function scopeSearchRelevance(Builder $query, string $search): Builder
    {
        $search = trim(preg_replace('/\s+/u', ' ', $search));
        $prefix = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
        return $query->orderByRaw("CASE WHEN sku = ? THEN 0 WHEN name = ? THEN 1 WHEN sku LIKE ? ESCAPE '!' THEN 2 WHEN name LIKE ? ESCAPE '!' THEN 3 ELSE 4 END", [$search, $search, $prefix, $prefix]);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function catalogCategory() { return $this->belongsTo(ProductCategory::class, 'catalog_category_id'); }

    /**
     * Уникальный slug для URL карточки (как на витрине-референсе).
     */
    public function freshUniqueSlug(): string
    {
        // Нельзя обрезать «сырое» имя до slugify: у прайса отличия (наклейка / конверт / BOX) часто в конце строки —
        // тогда получался одинаковый slug при одном PN и падало сохранение по unique(slug).
        $namePart = Str::slug((string) $this->name);
        $namePart = Str::limit($namePart, 160, '');
        if ($namePart === '') {
            $namePart = 'product';
        }

        $skuPart = filled($this->sku) ? Str::slug((string) $this->sku) : '';
        $base = $skuPart !== '' ? $namePart . '-' . $skuPart : $namePart;

        $slug = $base;
        $n = 0;
        while (static::query()
            ->where('slug', $slug)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    public function getDisplayTitleAttribute(): string
    {
        return trim(preg_split('/\s+формат поставки\s*[-—:]?/iu', $this->name, 2)[0]);
    }

    public function getSupplyFormatAttribute(): ?string
    {
        return preg_match('/формат поставки\s*[-—:]?\s*(.+?)(?:\s*\(вкл\.|$)/iu', $this->name, $matches)
            ? trim($matches[1]) : null;
    }

    public function getFormattedPriceAttribute(): string
    {
        if ($this->price === null || (float) $this->price <= 0) {
            return 'По запросу';
        }

        return number_format($this->price, 2, ',', ' ') . ' ' . ($this->currency === 'RUB' ? '₽' : $this->currency);
    }
}
