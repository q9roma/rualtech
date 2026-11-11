<?php
namespace App\Admin;

use App\Models\CatalogPriceUpload;
use Illuminate\Support\Facades\Storage;

class CatalogPriceComposition
{
    public static function compare(CatalogPriceUpload $upload, array $current): array
    {
        $previous=CatalogPriceUpload::where('source_profile',$upload->source_profile)->where('status','completed')
            ->where('created_at','<',$upload->created_at)->orderByDesc('created_at')->first();
        $empty=['baseline'=>null,'appeared'=>[],'missing'=>[]];
        if (!$previous) return $empty;
        $path=$previous->directory().'/staged-products.json';
        if (!Storage::disk('local')->exists($path)) return $empty;
        $json=Storage::disk('local')->get($path);
        if (!hash_equals($previous->staged_hash ?? '',hash('sha256',$json))) return $empty;
        $before=json_decode($json,true,flags:JSON_THROW_ON_ERROR);
        $identity=fn($r)=>$upload->source_profile==='supplier-price-32'
            ? (filled($r['supplier_id'] ?? null) ? hash('sha256','supplier-id:'.trim($r['supplier_id'])) : null)
            : (filled($r['brand'] ?? null) && filled($r['sku'] ?? null) ? CatalogDraftImport::identity($r['brand'],$r['sku'],$r['name']) : null);
        $oldKeys=[]; $newKeys=[];
        foreach($before as $r) if($key=$identity($r)) $oldKeys[$key]=true;
        foreach($current as $r) if($key=$identity($r)) $newKeys[$key]=true;
        return ['baseline'=>['id'=>$previous->id,'name'=>$previous->name],
            'appeared'=>array_values(array_map(fn($r)=>$r['row'],array_filter($current,fn($r)=>($key=$identity($r)) && !isset($oldKeys[$key])))),
            'missing'=>array_values(array_filter($before,fn($r)=>($key=$identity($r)) && !isset($newKeys[$key])))];
    }
}
