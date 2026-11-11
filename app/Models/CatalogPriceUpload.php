<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogPriceUpload extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['summary' => 'array', 'safety' => 'array'];
    public function directory(): string { return 'catalog-prices/'.$this->user_id.'/'.$this->id; }
}
