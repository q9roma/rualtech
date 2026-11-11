<?php
namespace Tests\Feature;

use App\Models\{CatalogPriceUpload, Product, User};
use App\Support\CatalogPhotoQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPhotoQueueTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(): Product
    {
        Storage::fake('local');
        $p=Product::create(['name'=>'Monitor','brand'=>'LG','sku'=>'M1','price'=>100,'is_active'=>false]);
        $user=User::factory()->create();
        $json=json_encode([['supplier_id'=>'one']]);
        $u=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'current.xlsx','status'=>'completed','source_profile'=>'supplier-price-32','file_hash'=>hash('sha256','file'),'staged_hash'=>hash('sha256',$json)]);
        Storage::disk('local')->put($u->directory().'/staged-products.json',$json);
        DB::table('catalog_source_products')->insert(['product_id'=>$p->id,'source'=>'supplier-price-32','identity_hash'=>hash('sha256','supplier-id:one'),'supplier_data'=>json_encode(['EAN'=>'123']),'created_at'=>now(),'updated_at'=>now()]);
        return $p;
    }
    public function test_repeat_planning_and_price_changes_do_not_duplicate_tasks_but_identity_changes_do(): void
    {
        $p=$this->fixture(); $queue=new CatalogPhotoQueue;
        $queue->plan(); $queue->plan();
        $this->assertDatabaseCount('catalog_photo_tasks',1);
        $p->update(['price'=>200,'availability'=>'9']); $queue->plan();
        $this->assertDatabaseCount('catalog_photo_tasks',1);
        DB::table('catalog_source_products')->update(['supplier_data'=>json_encode(['EAN'=>'456'])]);
        $queue->plan();
        $this->assertDatabaseCount('catalog_photo_tasks',2);
        $this->assertSame(1,DB::table('catalog_photo_tasks')->where('status','superseded')->count());
        $this->assertSame(1,DB::table('catalog_photo_tasks')->where('status','queued')->count());
        $this->assertFalse($p->fresh()->is_active);
    }
    public function test_selected_gallery_is_preserved_without_network_work(): void
    {
        $p=$this->fixture(); $file=str_repeat('a',64).'.jpg';
        Storage::disk('local')->put('catalog-media-queue/media/'.$file,'fixture');
        DB::table('catalog_approved_media')->insert(['product_id'=>$p->id,'task_id'=>0,'user_id'=>null,'origin'=>'automatic','identity'=>json_encode(['ean_conflict'=>false,'ean'=>'123','name'=>'Monitor','sku'=>'M1','brand'=>'LG','id'=>$p->id]),'images'=>json_encode([['file'=>$file]]),'created_at'=>now(),'updated_at'=>now()]);
        $queue=new CatalogPhotoQueue; $queue->plan(); $queue->plan();
        $this->assertDatabaseHas('catalog_photo_tasks',['status'=>'preserved','attempts'=>0]);
        $this->assertNull($queue->claim());
        $this->assertSame('fixture',Storage::disk('local')->get('catalog-media-queue/media/'.$file));
        $p->update(['sku'=>'M2']);
        $queue->plan();
        $this->assertDatabaseHas('catalog_photo_tasks',['product_id'=>$p->id,'status'=>'queued']);
        $this->assertNotNull($queue->claim());
    }
    public function test_expired_worker_is_reclaimed_and_old_worker_cannot_overwrite_result(): void
    {
        $this->fixture(); $queue=new CatalogPhotoQueue; $queue->plan();
        $old=$queue->claim(); $this->assertNull($queue->claim());
        $this->travel(4)->minutes(); $new=$queue->claim();
        $this->assertSame($old->id,$new->id); $this->assertNotSame($old->lease_token,$new->lease_token);
        $queue->finish($old,['status'=>'ready']);
        $this->assertDatabaseHas('catalog_photo_tasks',['id'=>$new->id,'status'=>'processing']);
        $queue->finish($new,['status'=>'retry','error'=>'provider unavailable']);
        $this->assertNull($queue->claim());
        $this->travel(31)->minutes(); $third=$queue->claim();
        $queue->finish($third,['status'=>'retry','error'=>'provider unavailable']);
        $this->assertDatabaseHas('catalog_photo_tasks',['id'=>$new->id,'status'=>'failed','attempts'=>3]);
    }
    public function test_negative_results_have_a_cooldown_and_do_not_publish(): void
    {
        $p=$this->fixture();$before=$p->fresh()->getRawOriginal();$queue=new CatalogPhotoQueue;$queue->plan();
        $queue->finish($queue->claim(),['status'=>'not_found']);$queue->plan();
        $this->assertNull($queue->claim());$this->travel(8)->days();
        $this->assertNotNull($queue->claim());
        $this->assertSame($before,$p->fresh()->getRawOriginal());
    }
    public function test_error_filter_includes_denied_access_without_double_counting_progress(): void
    {
        $this->fixture();
        (new CatalogPhotoQueue)->plan();
        $task=DB::table('catalog_photo_tasks')->first();
        DB::table('catalog_photo_tasks')->where('id',$task->id)->update(['status'=>'restricted']);
        $copy=(array)$task; unset($copy['id']);
        $copy['identity_hash']=hash('sha256','failed'); $copy['status']='failed';
        DB::table('catalog_photo_tasks')->insert($copy);
        \Illuminate\Support\Facades\Gate::before(fn()=>true);
        $this->actingAs(User::factory()->create());
        $controller=app(\App\Http\Controllers\Admin\CatalogPhotoQueueController::class);
        $data=$controller->data(\Illuminate\Http\Request::create('/','GET',['status'=>'failed']));
        $this->assertSame(2,$data['tasks']->total());
        $this->assertEquals(2,$data['total']);
        $this->assertEquals(2,$data['completed']);
    }

}


