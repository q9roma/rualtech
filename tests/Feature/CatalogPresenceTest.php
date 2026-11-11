<?php
namespace Tests\Feature;

use App\Admin\CatalogDraftImport;
use App\Models\{Product, User, CatalogPriceUpload};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogPresenceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->withoutVite(); Storage::fake('local'); config(['storefront.inertia'=>true,'inertia.ssr.enabled'=>false]); }
    public function test_source_and_full_file_presence_are_independent_and_ignore_partial_batches(): void
    {
        $user=User::factory()->create(['is_admin'=>true]);$this->actingAs($user);
        $a=Product::create(['name'=>'A','sku'=>'A','brand'=>'HP','is_active'=>false]);
        $b=Product::create(['name'=>'B','sku'=>'B','brand'=>'HP','is_active'=>false]);
        $c=Product::create(['name'=>'C','is_active'=>false]);
        foreach ([[$a,'altech-price','old-a'],[$a,'supplier-price-32',hash('sha256','supplier-id:one')],[$b,'supplier-price-32',hash('sha256','supplier-id:two')]] as [$p,$source,$hash]) DB::table('catalog_source_products')->insert(['product_id'=>$p->id,'source'=>$source,'identity_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
        $json=json_encode([['supplier_id'=>'one']]);
        $upload=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'current.xlsx','source_profile'=>'supplier-price-32','status'=>'completed','file_hash'=>hash('sha256','test'),'staged_hash'=>hash('sha256',$json)]);
        Storage::disk('local')->put($upload->directory().'/staged-products.json',$json);
        DB::table('catalog_import_batches')->insert(['source'=>'supplier-price-32','file_hash'=>hash('sha256','partial'),'rules_version'=>'partial','status'=>'completed','created_at'=>now(),'updated_at'=>now()]);
        $this->get('/admin/products?catalog_source=supplier-price-32')->assertInertia(fn(Assert $p)=>$p->where('records.total',2));
        $this->get('/admin/products?catalog_source=altech-price')->assertInertia(fn(Assert $p)=>$p->where('records.total',1));
        $this->get('/admin/products?catalog_source=unlinked')->assertInertia(fn(Assert $p)=>$p->where('records.total',1));
        $this->get('/admin/products?current_price=present')->assertInertia(fn(Assert $p)=>$p->where('records.total',1)->where('records.data.0.id',$a->id));
        $this->get('/admin/products?catalog_source=supplier-price-32&current_price=absent')->assertInertia(fn(Assert $p)=>$p->where('records.total',1)->where('records.data.0.id',$b->id));
        $this->get('/admin/products?catalog_source=altech-price&current_price=absent')->assertInertia(fn(Assert $p)=>$p->where('records.total',0)->where('presenceInfo.file',null));
        $this->get('/admin/products?current_price=present&search=B')->assertInertia(fn(Assert $p)=>$p->where('records.total',0));
    }
    public function test_draft_preview_is_private_and_does_not_publish(): void
    {
        $product=Product::create(['name'=>'Test draft','sku'=>'DRAFT','price'=>123,'is_active'=>false]);
        $description = "Описание поставщика\n<script>alert('unsafe')</script>";
        DB::table('catalog_source_products')->insert(['product_id'=>$product->id,'source'=>'supplier-price-32','identity_hash'=>hash('sha256','description'),'supplier_data'=>json_encode(['Описание'=>$description]),'created_at'=>now(),'updated_at'=>now()]);
        $this->assertSame($description, \App\Support\Storefront::supplierDescription($product));
        $this->assertArrayNotHasKey('supplierDescription', \App\Support\Storefront::product($product));
        $url='/admin/product-preview/'.$product->id;
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]));
        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $this->get($url)->assertOk()->assertHeader('X-Robots-Tag','noindex, nofollow')->assertInertia(fn(Assert $p)=>$p->component('products/show')->where('preview',true)->where('previewIsPublished',false)->where('product.sku','DRAFT')->where('product.supplierDescription',$description));
        $this->get('/product/'.$product->id)->assertNotFound();
        $this->assertFalse($product->fresh()->is_active);
    }
}
