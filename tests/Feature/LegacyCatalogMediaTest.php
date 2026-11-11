<?php
namespace Tests\Feature;
use App\Models\Product;
use App\Support\{LegacyCatalogMedia,ProductMediaQuality};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class LegacyCatalogMediaTest extends TestCase
{
    use RefreshDatabase;
    public function test_only_displayed_legacy_photos_become_assignments_and_drafts_remain_candidates(): void
    {
        Storage::fake('local'); $rows=[]; $products=[];
        foreach (['live','candidate','uncertain'] as $key) {
            $p=Product::create(['name'=>$key,'brand'=>'Brand','sku'=>$key,'is_active'=>$key==='live']); $products[$key]=$p;
            $file=hash('sha256',$key).'.jpg'; Storage::disk('local')->put('catalog-media-queue/media/'.$file,$key);
            $rows[]=['id'=>$p->id,'name'=>$key,'brand'=>'Brand','sku'=>$key,'ean'=>'123','vvp_status'=>$key==='uncertain' ? 'missing' : 'matched','gallery'=>[['file'=>$file,'source'=>$key==='uncertain' ? 'Icecat' : 'VVP','flags'=>[]]]];
        }
        Storage::disk('local')->put('catalog-automation/photo-quality/review.json',json_encode($rows));
        $this->assertSame([],ProductMediaQuality::images($products['live']));
        $service=new LegacyCatalogMedia;
        $preview=$service->migrate();
        $this->assertSame(1,$preview['assigned']); $this->assertSame(1,$preview['ready']); $this->assertSame(1,$preview['review']);
        $this->assertDatabaseCount('catalog_approved_media',0);
        $service->migrate(true);
        $this->assertDatabaseHas('catalog_approved_media',['product_id'=>$products['live']->id,'origin'=>'legacy_display','user_id'=>null]);
        $this->assertDatabaseHas('catalog_photo_tasks',['product_id'=>$products['candidate']->id,'status'=>'ready']);
        $this->assertDatabaseHas('catalog_photo_tasks',['product_id'=>$products['uncertain']->id,'status'=>'review']);
        $this->assertCount(1,ProductMediaQuality::images($products['live']));
        $this->assertSame([],ProductMediaQuality::images($products['candidate']));
        $service->migrate(true);
        $this->assertDatabaseCount('catalog_approved_media',1);
        $this->assertSame(1,DB::table('catalog_product_events')->where('kind','photos_assigned')->count());
    }
}
