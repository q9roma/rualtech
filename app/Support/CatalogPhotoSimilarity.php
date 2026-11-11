<?php
namespace App\Support;

use Illuminate\Support\Facades\{DB,Storage};

class CatalogPhotoSimilarity
{
    public const MAX_DISTANCE=6;
    private ?array $catalog=null;
    private array $hashes=[];
    private array $found=[];

    public function fingerprint(string $file,string $directory='catalog-media-queue/media'): ?string
    {
        if (!preg_match('/\A[a-f0-9]{64}\.(jpg|png|webp)\z/',$file)) return null;
        $disk=Storage::disk('local');
        if (!$disk->exists($directory.'/'.$file)) return null;
        if (array_key_exists($file,$this->hashes)) return $this->hashes[$file];
        $cache='catalog-media-queue/fingerprints/'.PhotoPerceptualHash::VERSION.'/'.$file.'.json';
        if ($disk->exists($cache)) return $this->hashes[$file]=json_decode($disk->get($cache),true)['hash'] ?? null;
        $hash=PhotoPerceptualHash::fromFile($disk->path($directory.'/'.$file));
        $disk->put($cache,json_encode(['hash'=>$hash]));
        return $this->hashes[$file]=$hash;
    }

    public function catalog(): array
    {
        if ($this->catalog!==null) return $this->catalog;
        $items=[];
        $products=DB::table('products')->get(['id','name','sku','brand'])->keyBy('id');
        $append=function(array $image,int $productId,string $directory,string $url) use (&$items,$products) {
            $product=$products->get($productId);if (!$product) return;
            $hash=$this->fingerprint($image['file'] ?? '',$directory);
            if (!$hash) return;
            $id=hash('sha256',$productId.'|'.$image['file']);
            $items[$id]=['id'=>$id,'productId'=>$productId,'name'=>$product->name,'sku'=>$product->sku,'url'=>$url,'hash'=>$hash,'directory'=>$directory,'image'=>$image];
        };
        foreach(DB::table('catalog_photo_tasks')->whereNotIn('status',['superseded','rejected'])->orderBy('id')->get(['id','product_id','payload','result']) as $task) {
            $product=$products->get($task->product_id);$payload=json_decode($task->payload,true) ?? [];
            if (!$product || collect(['name','sku','brand'])->contains(fn($key)=>($payload[$key] ?? '')!==(string)$product->$key)) continue;
            foreach((json_decode($task->result ?? '{}',true)['images'] ?? []) as $index=>$image) $append($image,$task->product_id,'catalog-media-queue/media',url('/admin/catalog-photo-queue/'.$task->id.'/image/'.$index));
        }
        foreach(DB::table('catalog_approved_media')->get() as $assignment) {
            $product=$products->get($assignment->product_id); $identity=json_decode($assignment->identity,true);
            if (!$product || collect(['name','sku','brand'])->contains(fn($key)=>($identity[$key] ?? '')!==(string)$product->$key)) continue;
            foreach(json_decode($assignment->images,true) as $index=>$image) $append($image,$product->id,'catalog-media-queue/media',url('/admin/products/'.$product->id.'/assigned-photo/'.$index));
        }
        return $this->catalog=array_values($items);
    }

    public function matches(array $image,int $productId): array
    {
        $key=$productId.'|'.($image['file'] ?? '');
        if (isset($this->found[$key])) return $this->found[$key];
        $hash=$this->fingerprint($image['file'] ?? '');if (!$hash) return [];
        $matches=[];
        foreach($this->catalog() as $candidate) {
            if ($candidate['productId']===$productId) continue;
            $exact=$candidate['image']['file']===$image['file'];
            $distance=$exact ? 0 : PhotoPerceptualHash::distance($hash,$candidate['hash']);
            $a=($image['width'] ?? 0)/max(1,$image['height'] ?? 0);
            $b=($candidate['image']['width'] ?? 0)/max(1,$candidate['image']['height'] ?? 0);
            if (!$exact && ($distance>self::MAX_DISTANCE || ($a>0 && $b>0 && max($a/$b,$b/$a)>1.25))) continue;
            $matches[]=[...$candidate,'distance'=>$distance,'exact'=>$exact];
        }
        usort($matches,fn($a,$b)=>($b['exact']<=>$a['exact']) ?: ($a['distance']<=>$b['distance']) ?: ($a['productId']<=>$b['productId']));
        return $this->found[$key]=$matches;
    }
    public function present(array $match): array
    {
        $image=$match['image'];
        return ['id'=>$match['id'],'productId'=>$match['productId'],'name'=>$match['name'],'sku'=>$match['sku'],'url'=>$match['url'],'source'=>$image['source'] ?? '', 'width'=>$image['width'] ?? 0,'height'=>$image['height'] ?? 0,'flags'=>ApprovedProductMedia::blockingFlags($image),'distance'=>$match['distance'],'exact'=>$match['exact']];
    }
    public function photo(array $image,int $taskId,int $index,int $productId): array
    {
        return ['url'=>url('/admin/catalog-photo-queue/'.$taskId.'/image/'.$index),'source'=>$image['source'] ?? '', 'width'=>$image['width'] ?? 0,'height'=>$image['height'] ?? 0,'flags'=>ApprovedProductMedia::blockingFlags($image),'matchesCount'=>count($this->matches($image,$productId))];
    }
}
