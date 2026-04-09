<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'category',
        'brand',
        'sku',
        'name',
        'slug',
        'availability',
        'price',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'price'     => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

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

    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price) {
            return 'По запросу';
        }

        return number_format($this->price, 0, ',', ' ') . ' ' . $this->currency;
    }
}
