<?php
namespace Tests\Feature;

use App\Admin\CatalogHistory;
use App\Models\{Product,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductHistoryPresentationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp(); $this->withoutVite(); config(['inertia.ssr.enabled'=>false]);
        Storage::fake('local'); $this->actingAs(User::factory()->create(['is_admin'=>true]));
    }

    public function test_filter_searches_entire_history_and_keeps_pagination_filter(): void
    {
        $p=Product::create(['name'=>'Товар']);
        for ($i=0;$i<32;$i++) CatalogHistory::record($p->id,'price_updated',['price'=>['before'=>100,'after'=>101]]);
        CatalogHistory::record($p->id,'edited',['description'=>['before'=>'До','after'=>'После']]);
        $this->get('/admin/products/'.$p->id.'/history?filter=price&page=2')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->where('filter','price')->where('events.total',32)->has('events.data',2)
            ->where('events.prev_page_url',fn($url)=>str_contains($url,'filter=price')));
        $this->get('/admin/products/'.$p->id.'/history?filter=photos')->assertInertia(fn(Assert $page)=>$page->where('events.total',0));
        $this->get('/admin/products/'.$p->id.'/history?filter=bad')->assertSessionHasErrors('filter');
    }

    public function test_card_embeds_filtered_history_and_pagination_keeps_history_tab(): void
    {
        $p=Product::create(['name'=>'Товар']);
        for ($i=0;$i<31;$i++) CatalogHistory::record($p->id,'price_updated',['price'=>['before'=>100,'after'=>120]]);
        $url='/admin/products/'.$p->id.'/edit';
        $this->get($url.'?tab=history&filter=price')->assertOk()->assertInertia(fn(Assert $page)=>$page
            ->component('Admin/Form')->where('productTab','history')->where('productHistory.events.total',31)
            ->where('productHistory.events.next_page_url',fn($url)=>str_contains($url,'/edit?') && str_contains($url,'tab=history') && str_contains($url,'filter=price')));
        $this->get($url.'?tab=source')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('productTab','source')->has('record.priceSources'));
        $this->get('/admin/products/'.$p->id.'?tab=history')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('readOnly',true)->where('productTab','history'));
        $this->get($url.'?tab=wrong')->assertSessionHasErrors('tab');
    }

    public function test_unknown_freshness_is_neutral_and_source_data_is_on_card(): void
    {
        $p=Product::create(['name'=>'Товар']);
        DB::table('catalog_source_products')->insert(['product_id'=>$p->id,'source'=>'altech-price','identity_hash'=>hash('sha256','x'),'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame('unknown',CatalogHistory::sources($p->id)[0]['freshness']);
        $this->assertFalse(CatalogHistory::sources($p->id)[0]['stale']);
        $this->get('/admin/products/'.$p->id.'/edit')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('record.priceSources.0.freshness','unknown'));
        DB::table('catalog_source_products')->update(['last_validated_at'=>now()->subHours(49)]);
        $this->assertSame('stale',CatalogHistory::sources($p->id)[0]['freshness']);
    }

    public function test_source_file_is_the_one_that_changed_this_product(): void
    {
        $p=Product::create(['name'=>'Товар']);
        DB::table('catalog_source_products')->insert(['product_id'=>$p->id,'source'=>'altech-price','identity_hash'=>hash('sha256','source'),'created_at'=>now(),'updated_at'=>now()]);
        $batch=DB::table('catalog_import_batches')->insertGetId(['source'=>'altech-price','file_hash'=>str_repeat('a',64),'rules_version'=>'test','status'=>'completed','created_at'=>now(),'updated_at'=>now()]);
        CatalogHistory::record($p->id,'price_updated',['price'=>['before'=>100,'after'=>120]],[],'altech-price',$batch);
        $old=\App\Models\CatalogPriceUpload::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>auth()->id(),'name'=>'Обновил товар.xlsx','source_profile'=>'altech-price','status'=>'completed','file_hash'=>str_repeat('a',64),'summary'=>['batch_id'=>$batch]]);
        $this->travel(1)->minutes();
        \App\Models\CatalogPriceUpload::create(['id'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>auth()->id(),'name'=>'Другой файл.xlsx','source_profile'=>'altech-price','status'=>'completed','file_hash'=>str_repeat('b',64),'summary'=>['batch_id'=>$batch+1]]);
        $this->assertSame($old->id,CatalogHistory::sources($p->id)[0]['upload']['id']);
        DB::table('catalog_product_events')->where('product_id',$p->id)->delete();
        $this->assertNull(CatalogHistory::sources($p->id)[0]['upload']);
    }

    public function test_historical_photos_are_scoped_to_product_and_require_staff_access(): void
    {
        $p=Product::create(['name'=>'Товар']); $other=Product::create(['name'=>'Другой']);
        Storage::disk('local')->put('product-photos/old.jpg','photo');
        CatalogHistory::record($p->id,'edited',['photos'=>['before'=>json_encode(['product-photos/old.jpg']),'after'=>[]]]);
        $id=DB::table('catalog_product_events')->latest('id')->value('id');
        $url='/admin/products/'.$p->id.'/history/'.$id.'/photo/before/0';
        $this->get($url)->assertOk();
        $this->get('/admin/products/'.$other->id.'/history/'.$id.'/photo/before/0')->assertNotFound();
        $this->get('/admin/products/'.$p->id.'/history')->assertInertia(fn(Assert $page)=>$page->where('events.data.0.photoPreviews.before.0',$url));
        $this->actingAs(User::factory()->create(['is_admin'=>false])); $this->get($url)->assertForbidden();
    }
}
