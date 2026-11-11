<?php
namespace Tests\Feature;

use App\Admin\{CatalogDraftImport,CatalogHistory,CatalogMissingStock,CatalogPriceSafety,CatalogPriceWorkflow};
use App\Models\{CatalogPriceUpload,ContactRequest,Product,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Mail,Queue,Storage};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled'=>false]);
        Storage::fake('local'); Mail::fake(); Queue::fake();
    }

    private function row(string $id='1'): array
    {
        return ['row'=>(int)$id+1,'supplier_id'=>$id,'brand'=>'HP','sku'=>'TEST-'.$id,'name'=>'Ноутбук HP '.$id,
            'source_category'=>'IT','status'=>'include','rule'=>'laptop','section'=>'Компьютеры и ноутбуки','subcategory'=>'Ноутбуки',
            'errors'=>[],'stock'=>['5','5','0'],'price'=>'100.00','currency'=>'RUB',
            'supplier_data'=>['Бренд'=>'HP','Артикул'=>'TEST-'.$id,'EAN'=>'123'.$id]];
    }

    private function runImport(array $rows): array
    {
        return app(CatalogDraftImport::class)->run($rows,'supplier-price-32',hash('sha256',json_encode($rows)),'test',true);
    }

    private function upload(array $rows, string $status='ready'): CatalogPriceUpload
    {
        $user=auth()->user() ?? User::factory()->create(['is_admin'=>true]);
        $json=json_encode($rows);
        $upload=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'price.xlsx','source_profile'=>'supplier-price-32',
            'price_date'=>today()->toDateString(),'status'=>$status,'file_hash'=>hash('sha256',$json),'staged_hash'=>hash('sha256',$json)]);
        Storage::disk('local')->put($upload->directory().'/staged-products.json',$json);
        return $upload;
    }

    public function test_history_records_manual_edits_and_preview_leaves_no_trace(): void
    {
        $user=User::factory()->create(['is_admin'=>true]); $this->actingAs($user);
        $this->runImport([$this->row()]); $p=Product::firstOrFail();
        $p->update(['name'=>'Наше название','photos'=>['product-photos/a.jpg']]);
        $before=DB::table('catalog_product_events')->count();
        $row=[...$this->row(),'price'=>'150'];
        app(CatalogPriceWorkflow::class)->preview([$row],hash('sha256','next'),'supplier-price-32');
        $this->assertDatabaseCount('catalog_product_events',$before);
        $this->assertSame('100.00',$p->fresh()->price);
        $this->runImport([$row]);
        $p->refresh(); $this->assertSame('Наше название',$p->name); $this->assertSame(['product-photos/a.jpg'],$p->photos);
        $event=DB::table('catalog_product_events')->where('kind','price_updated')->first();
        $this->assertSame('100.00',json_decode($event->changes,true)['price']['before']);
        $this->assertSame($user->id,$event->user_id);
        $count=DB::table('catalog_product_events')->count(); $this->runImport([$row]);
        $this->assertDatabaseCount('catalog_product_events',$count);
        $this->get('/admin/products/'.$p->id.'/history')->assertOk()->assertInertia(fn(Assert $page)=>$page->component('Admin/ProductHistory')->where('product.id',$p->id)->has('sources',1));
        $this->actingAs(User::factory()->create(['is_admin'=>false]));
        $this->get('/admin/products/'.$p->id.'/history')->assertForbidden();
    }

    public function test_editing_just_created_instance_is_not_another_creation(): void
    {
        $product=Product::create(['name'=>'Original']);
        $product->update(['name'=>'Edited']); $product->save();
        $this->assertSame(1,DB::table('catalog_product_events')->where('kind','created')->count());
        $this->assertSame(1,DB::table('catalog_product_events')->where('kind','edited')->count());
    }

    public function test_changed_supplier_identity_cannot_overwrite_card_or_saved_source(): void
    {
        $row=$this->row(); $this->runImport([$row]);
        foreach (['brand'=>'Other','sku'=>'OTHER'] as $field=>$value) {
            $summary=$this->runImport([[...$row,$field=>$value,'price'=>'999']]);
            $this->assertSame(1,$summary['held']);
        }
        $summary=$this->runImport([[...$row,'supplier_data'=>[...$row['supplier_data'],'EAN'=>'DIFFERENT'],'price'=>'999']]);
        $this->assertSame(1,$summary['held']);
        $this->assertSame('100.00',Product::first()->price);
        $this->assertSame('1231',json_decode(DB::table('catalog_source_products')->first()->supplier_data,true)['EAN']);
        $this->assertDatabaseCount('products',1);
    }

    public function test_missing_and_return_are_logged_once_and_preserve_card(): void
    {
        $this->runImport([$this->row(),$this->row('2')]); $p=Product::where('sku','TEST-1')->first();
        CatalogMissingStock::apply([$this->row('2')],'supplier-price-32');
        $this->assertSame('0',$p->fresh()->availability);
        $this->assertDatabaseHas('catalog_product_events',['product_id'=>$p->id,'kind'=>'missing']);
        $count=DB::table('catalog_product_events')->count();
        CatalogMissingStock::apply([$this->row('2')],'supplier-price-32');
        $this->assertDatabaseCount('catalog_product_events',$count);
        $this->runImport([$this->row(),$this->row('2')]);
        $this->assertSame('5',$p->fresh()->availability);
        $this->assertDatabaseHas('catalog_product_events',['product_id'=>$p->id,'kind'=>'returned']);
        $this->assertNull(DB::table('catalog_source_products')->where('product_id',$p->id)->value('missing_since'));
    }

    public function test_safety_rejects_duplicates_and_old_dates_and_detects_replaced_assortment(): void
    {
        $rows=[$this->row(),$this->row('2')]; $previous=$this->upload($rows,'completed');
        $duplicate=$this->upload($rows);
        $this->assertNotEmpty(CatalogPriceSafety::inspect($duplicate,$rows)['blocked']);
        $different=[$this->row('3'),$this->row('4')]; $next=$this->upload($different);
        $safety=CatalogPriceSafety::inspect($next,$different);
        $this->assertEmpty($safety['blocked']); $this->assertNotEmpty($safety['warnings']);
        $next->update(['price_date'=>today()->subDay()->toDateString()]);
        $this->assertNotEmpty(CatalogPriceSafety::inspect($next,$different)['blocked']);
        $previous->update(['staged_hash'=>hash('sha256','[]')]);
        $this->assertNotEmpty(CatalogPriceSafety::inspect($next,$different)['blocked']);
        Storage::disk('local')->put($previous->directory().'/staged-products.json','[]');
        $next->update(['price_date'=>today()->toDateString()]);
        $this->assertNotEmpty(CatalogPriceSafety::inspect($next,$different)['blocked']);
    }

    public function test_warning_requires_matching_acknowledgement_and_worker_rechecks(): void
    {
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->upload([$this->row(),$this->row('2')],'completed');
        $rows=[$this->row('3')]; $upload=$this->upload($rows);
        $preview=app(CatalogPriceWorkflow::class)->preview($rows,$upload->file_hash,'supplier-price-32');
        $upload->update(['fingerprint'=>$preview['fingerprint'],'summary'=>$preview['summary']]);
        $url='/admin/catalog-prices/'.$upload->id.'/run';
        $this->post($url)->assertSessionHasErrors('safety_ack');
        $this->assertSame('ready',$upload->fresh()->status);
        $safety=CatalogPriceSafety::inspect($upload,$rows);
        $this->post($url,['safety_ack'=>$safety['token']])->assertSessionHasNoErrors();
        $this->assertSame('applying',$upload->fresh()->status);
        // A newer successful upload invalidates the warning acknowledgement, even without product edits.
        $this->travel(1)->minutes(); $this->upload([$this->row('4')],'completed');
        app(CatalogPriceWorkflow::class)->apply($upload);
        $this->assertSame('stale',$upload->fresh()->status); $this->assertDatabaseCount('products',0);
    }

    public function test_freshness_does_not_refresh_on_manual_edit(): void
    {
        $this->runImport([$this->row()]); $p=Product::first();
        $this->assertFalse(CatalogHistory::sources($p->id)[0]['stale']);
        $this->travel(49)->hours(); $p->update(['name'=>'Новое название']);
        $this->assertTrue(CatalogHistory::sources($p->id)[0]['stale']);
        $this->runImport([$this->row()]);
        $this->assertFalse(CatalogHistory::sources($p->id)[0]['stale']);
    }

    public function test_saved_item_decision_is_reused_only_for_same_identity(): void
    {
        \App\Support\CatalogTaxonomy::seed();
        $row=[...$this->row(),'status'=>'review'];
        \App\Models\CatalogImportMapping::create(['mapping_key'=>CatalogDraftImport::itemKey('supplier-price-32',$row),
            'source'=>'supplier-price-32','source_category'=>'IT','rule_code'=>'item-decision','name'=>'Reviewed item','decision'=>'include',
            'catalog_category_id'=>\App\Models\ProductCategory::where('name','Ноутбуки')->first()->id,'manual_locked'=>true,'decision_identity'=>CatalogDraftImport::decisionIdentity($row)]);
        $changed=[...$row,'sku'=>'DIFFERENT'];
        $this->assertSame(1,$this->runImport([$changed])['held']);
        $this->assertDatabaseCount('products',0);
        $this->assertSame(1,$this->runImport([$row])['created_drafts']);
    }

    public function test_worker_applies_acknowledged_full_snapshot_and_records_actor(): void
    {
        $user=User::factory()->create(['is_admin'=>true]); $this->actingAs($user);
        $rows=[$this->row()]; $upload=$this->upload($rows);
        $preview=app(CatalogPriceWorkflow::class)->preview($rows,$upload->file_hash,'supplier-price-32');
        $upload->update(['fingerprint'=>$preview['fingerprint'],'summary'=>$preview['summary'],'status'=>'applying']);
        auth()->forgetGuards();
        app(CatalogPriceWorkflow::class)->apply($upload);
        $this->assertSame('completed',$upload->fresh()->status);
        $this->assertDatabaseHas('catalog_product_events',['kind'=>'created','user_id'=>$user->id,'source'=>'supplier-price-32']);
        $this->assertDatabaseCount('products',1);
    }

    public function test_supply_request_stores_trusted_product_and_quantity_and_rejects_draft(): void
    {
        $p=Product::create(['name'=>'Монитор','sku'=>'DISPLAY','is_active'=>true,'availability'=>'0']);
        $data=['name'=>'Покупатель','email'=>'buyer@example.test','service'=>'Поддельное название','product_id'=>$p->id,'quantity'=>4];
        $this->postJson('/contact/order-service',$data,['X-Requested-With'=>'XMLHttpRequest'])->assertOk()->assertJson(['success'=>true]);
        $request=ContactRequest::firstOrFail();
        $this->assertSame($p->id,$request->meta_data['product_id']); $this->assertSame(4,$request->meta_data['quantity']);
        $this->assertStringContainsString('Монитор',$request->service);
        $this->postJson('/contact/order-service',[...$data,'quantity'=>0],['X-Requested-With'=>'XMLHttpRequest'])->assertStatus(422);
        $p->update(['is_active'=>false]);
        $this->postJson('/contact/order-service',$data,['X-Requested-With'=>'XMLHttpRequest'])->assertStatus(422);
        $this->assertDatabaseCount('contact_requests',1);
    }
}
