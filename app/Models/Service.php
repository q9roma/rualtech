<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'short_description',
        'description',
        'icon',
        'price_from',
        'price_type',
        'features',
        'sort_order',
        'is_active',
        'is_featured',
        'seo_title',
        'seo_description',
    ];

    protected $casts = [
        'price_from' => 'decimal:2',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function getFormattedPriceAttribute(): string
    {
        if (!$this->price_from) {
            return 'По запросу';
        }

        return $this->price_type . ' ' . number_format($this->price_from, 0, ',', ' ') . ' ₽';
    }
}
