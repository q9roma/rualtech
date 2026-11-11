<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductCategory extends Model
{
    protected $fillable = ['code', 'name', 'slug', 'parent_id', 'sort_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (self $category) {
            if ($category->parent_id) {
                $parent = self::find($category->parent_id);
                if (! $parent || $parent->parent_id || $parent->id === $category->id || ($category->exists && $category->children()->exists())) {
                    throw ValidationException::withMessages(['parent_id' => 'Подкатегория может принадлежать только корневому разделу.']);
                }
            }
            if (! $category->exists) {
                if (! $category->code) $category->code = (string) Str::uuid();
                if (! $category->slug) $category->slug = Str::slug($category->name).'-'.substr($category->code, 0, 8);
            } elseif ($category->isDirty('slug') || $category->isDirty('code')) {
                throw ValidationException::withMessages(['slug' => 'Постоянный адрес категории менять нельзя.']);
            }
        });
    }

    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function children() { return $this->hasMany(self::class, 'parent_id'); }
    public function products() { return $this->hasMany(Product::class, 'catalog_category_id'); }

    public static function leafOptions(): array
    {
        return self::with('parent')->whereNotNull('parent_id')->orderBy('name')->get()
            ->mapWithKeys(fn ($c) => [$c->id => $c->parent->name.' / '.$c->name])->all();
    }
}
