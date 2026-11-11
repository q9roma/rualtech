<?php
namespace App\Http\Controllers\Admin;

use App\Admin\CatalogHistory;
use App\Models\Product;
use Illuminate\Support\Facades\{DB,Gate};
use Inertia\Inertia;

class ProductHistoryController
{
    public function show(\Illuminate\Http\Request $request, Product $product)
    {
        return Inertia::render('Admin/ProductHistory',$this->data($request,$product));
    }

    public function data(\Illuminate\Http\Request $request, Product $product, bool $embedded = false): array
    {
        Gate::authorize('view',$product);
        $request->validate(['filter'=>'nullable|in:all,price,content,photos,publication']);
        $filter=$request->input('filter','all');
        $fields=match($filter) {
            'price'=>['price','currency','availability','stock_free','stock_transit'],
            'content'=>['name','brand','sku','description','specifications','catalog_category_id','category','slug'],
            'photos'=>['photos'], 'publication'=>['is_active'], default=>[],
        };
        $kinds=match($filter) {'price'=>['price_updated','stock_cleared','missing','returned'],'photos'=>['photos_assigned'],default=>[]};
        $events=DB::table('catalog_product_events as e')->leftJoin('users as u','u.id','=','e.user_id')
            ->where('e.product_id',$product->id)->when($filter!=='all',fn($query)=>$query->where(function($q) use($fields,$kinds) {
                $q->whereIn('e.kind',$kinds);
                foreach($fields as $field) $q->orWhereNotNull('e.changes->'.$field);
            }))->select('e.*','u.name as actor')->orderByDesc('e.id')->paginate(30)->withQueryString();
        if ($embedded) $events->appends(['tab'=>'history']);
        $events->through(function($event) {
            $event->changes=json_decode($event->changes,true);
            $event->context=json_decode($event->context,true);
            $upload=$event->batch_id ? \App\Models\CatalogPriceUpload::where('summary->batch_id',$event->batch_id)->first() : null;
            $event->upload=$upload ? ['id'=>$upload->id,'name'=>$upload->name,'canOpen'=>$upload->user_id === auth()->id()] : null;
            $event->created_at=\Carbon\Carbon::parse($event->created_at,config('app.timezone'))->toIso8601String();
            $event->photoPreviews=[];
            foreach (['before','after'] as $side) foreach ($this->photoFiles($event,$side) as $index=>$file) {
                if (\Illuminate\Support\Facades\Storage::disk('local')->exists($file)) $event->photoPreviews[$side][]='/admin/products/'.$event->product_id.'/history/'.$event->id.'/photo/'.$side.'/'.$index;
            }
            return $event;
        });
        return ['product'=>$product->only('id','name'),'events'=>$events,'filter'=>$filter,'categories'=>DB::table('product_categories')->pluck('name','id'),'sources'=>CatalogHistory::sources($product->id)];
    }

    private function photoFiles(object $event,string $side): array
    {
        $changes=is_string($event->changes) ? json_decode($event->changes,true) : $event->changes;
        $context=is_string($event->context) ? json_decode($event->context,true) : $event->context;
        $value=$changes['photos'][$side] ?? [];
        if (is_string($value)) $value=json_decode($value,true);
        $files=array_values(array_filter(is_array($value) ? $value : [],fn($file)=>is_string($file) && preg_match('~\Aproduct-photos/[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z~',$file)));
        if ($side==='after' && $event->kind==='photos_assigned' && isset($context['task_id'])) {
            $task=DB::table('catalog_photo_tasks')->where('id',$context['task_id'])->where('product_id',$event->product_id)->first();
            foreach (json_decode($task->result ?? '{}',true)['images'] ?? [] as $image) {
                if (preg_match('/\A[a-f0-9]{64}\.(?:jpg|png|webp)\z/',$image['file'] ?? '')) $files[]='catalog-media-queue/media/'.$image['file'];
            }
        }
        return $files;
    }

    public function photo(Product $product,int $event,string $side,int $index)
    {
        Gate::authorize('view',$product);
        abort_unless(in_array($side,['before','after'],true),404);
        $record=DB::table('catalog_product_events')->where('product_id',$product->id)->where('id',$event)->first();
        abort_unless($record,404);
        $file=$this->photoFiles($record,$side)[$index] ?? null;
        abort_unless($file && \Illuminate\Support\Facades\Storage::disk('local')->exists($file),404);
        return \Illuminate\Support\Facades\Storage::disk('local')->response($file,null,['Cache-Control'=>'private, max-age=300','X-Content-Type-Options'=>'nosniff']);
    }
}
