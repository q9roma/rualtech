<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogImportMapping extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['manual_locked' => 'boolean'];
    public function category() { return $this->belongsTo(ProductCategory::class, 'catalog_category_id'); }
}
