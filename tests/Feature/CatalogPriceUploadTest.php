<?php
namespace Tests\Feature;

use App\Admin\{CatalogDraftImport, CatalogPriceWorkflow};
use App\Jobs\ProcessCatalogPrice;
use App\Models\{CatalogPriceUpload, Product, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Queue, Storage};
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogPriceUploadTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        Storage::fake('local');
    }
    public function test_item_decision_is_persistent_scoped_and_does_not_apply_catalog(): void
    {
        Queue::fake(); $this->staff();
        \App\Support\CatalogTaxonomy::seed();
        $row = [...$this->row(), 'status'=>'review', 'rule'=>'audio-review'];
        $other = [...$row, 'row'=>3, 'sku'=>'OTHER'];
        $upload = $this->ready([$row, $other]);
        $upload->update(['source_profile'=>'altech-price']);
        $category = \App\Models\ProductCategory::where('name','Ноутбуки')->firstOrFail();
        $url = '/admin/catalog-prices/'.$upload->id.'/decision';
        $this->post($url, ['row'=>2,'decision'=>'include'])->assertSessionHasErrors('category_id');
        $this->post($url, ['row'=>2,'decision'=>'include','category_id'=>$category->id])->assertRedirect();
        $this->assertSame(0, Product::count());
        $this->assertSame('queued', $upload->fresh()->status);
        Queue::assertPushed(ProcessCatalogPrice::class);
        $preview = app(CatalogPriceWorkflow::class)->preview([$row,$other], hash('sha256','next-file'));
        $this->assertSame(1, $preview['summary']['created_drafts']);
        $this->assertSame(1, $preview['summary']['held']);
        $this->assertSame(0, Product::count());
        $bad = [...$row, 'errors'=>['ambiguous_source_identity']];
        $this->assertSame(1, app(CatalogPriceWorkflow::class)->preview([$bad], hash('sha256','bad'))['summary']['held']);
        $upload->refresh()->update(['status'=>'ready']);
        $this->post($url, ['row'=>2,'decision'=>'exclude'])->assertRedirect();
        $this->assertSame(1, app(CatalogPriceWorkflow::class)->preview([$row], hash('sha256','excluded'))['summary']['excluded']);
        $upload->refresh()->update(['status'=>'ready']);
        $this->post($url, ['row'=>2,'decision'=>'reset'])->assertRedirect();
        $this->assertSame(1, app(CatalogPriceWorkflow::class)->preview([$row], hash('sha256','reset'))['summary']['held']);
        $upload->update(['status'=>'completed']);
        $this->post($url, ['row'=>2,'decision'=>'exclude'])->assertStatus(409);
    }

    public function test_photo_progress_returns_counts_without_rows_and_checks_upload_owner(): void
    {
        $this->staff();
        foreach (['queued','processing','ready','superseded'] as $i=>$status) DB::table('catalog_photo_tasks')->insert(['product_id'=>900+$i,'identity_hash'=>hash('sha256',$status),'upload_id'=>'test','payload'=>'{}','status'=>$status,'created_at'=>now(),'updated_at'=>now()]);
        $this->getJson('/admin/catalog-photo-queue/progress')->assertOk()
            ->assertJsonPath('total',3)->assertJsonPath('completed',1)
            ->assertJsonPath('counts.processing',1)->assertJsonMissingPath('tasks');
        $upload=$this->ready([$this->row()]);
        $this->getJson('/admin/catalog-photo-queue/progress?upload='.$upload->id)->assertOk()->assertJsonPath('total',0);
        $upload->update(['user_id'=>User::factory()->create()->id]);
        $this->getJson('/admin/catalog-photo-queue/progress?upload='.$upload->id)->assertNotFound();
    }

    public function test_missing_card_and_warning_use_same_previous_file_rows(): void
    {
        $this->staff();
        $previous=$this->ready([$this->row(), [...$this->row(), 'row'=>3, 'sku'=>'OLD']]);
        $previous->update(['status'=>'completed','source_profile'=>'altech-price','created_at'=>now()->subDay()]);
        $upload=$this->ready([$this->row()]);
        $upload->update(['source_profile'=>'altech-price','summary'=>[...$upload->summary,'absent'=>99]]);
        foreach (['membership=missing','anomaly=missing'] as $filter) {
            $this->get('/admin/catalog-prices/'.$upload->id.'?section=compare&'.$filter)
                ->assertOk()->assertInertia(fn(Assert $p)=>$p
                    ->where('comparison.missingCount',1)->where('comparison.total',1)
                    ->where('comparison.anomalies.2.count',1)
                    ->where('comparison.anomalies.2.label','Нет в новом прайсе')
                    ->where('details.0.sku','OLD'));
        }
    }

    public function test_anomaly_counts_and_filter_use_whole_report_and_survive_pagination(): void
    {
        $this->staff(); $upload=$this->ready([$this->row()]);
        $rows=[];
        for ($i=2;$i<62;$i++) $rows[]=['row'=>$i,'sku'=>'A'.$i,'name'=>'Test '.$i,'operation'=>'updated','price'=>'140','stock'=>['0','0','0'],'issues'=>[],
            'changes'=>['price'=>['before'=>'100','after'=>'140'],'availability'=>['before'=>'>50','after'=>'0']]];
        $rows[]=['row'=>62,'sku'=>'B','name'=>'Boundary','operation'=>'updated','price'=>'130','stock'=>['0','0','0'],'issues'=>[], 'changes'=>['price'=>['before'=>'100','after'=>'130']]];
        Storage::disk('local')->put($upload->directory().'/report.json',json_encode($rows));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=compare&anomaly=price&compare_page=2')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('comparison.total',60)->where('comparison.anomaly','price')->where('comparison.anomalies.0.count',60)->where('comparison.anomalies.1.count',60)->has('details',10));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=compare&anomaly=stock&compare_search=Test%202')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('comparison.total',11)->where('comparison.anomalies.1.count',60));
        $match=\App\Admin\CatalogPriceAnomalies::class;
        $this->assertFalse($match::matches(['changes'=>['price'=>['before'=>null,'after'=>100]]],'price'));
        $this->assertFalse($match::matches(['changes'=>['availability'=>['before'=>'0','after'=>'0']]],'stock'));
        $this->assertFalse($match::matches(['changes'=>['availability'=>['before'=>'8','after'=>null]]],'stock'));
        $this->assertTrue($match::matches(['operation'=>'absent'],'missing'));
        $this->assertTrue($match::matches(['issues'=>['ambiguous_source_identity']],'data'));
        $this->assertSame(0,Product::count());
    }

    public function test_bulk_release_assigns_only_ready_photos_and_publishes_only_reviewed_scope(): void
    {
        $user=$this->staff();
        $rows=[$this->row(),[...$this->row(),'row'=>3,'sku'=>'SECOND','name'=>'Ноутбук SECOND']];
        $summary=app(CatalogDraftImport::class)->run($rows,'altech-price',hash('sha256','release'),'test',true);
        $upload=$this->ready($rows);
        $upload->update(['status'=>'completed','source_profile'=>'altech-price','summary'=>$summary]);
        $products=Product::orderBy('id')->get();
        $file=str_repeat('a',64).'.png';
        Storage::disk('local')->put('catalog-media-queue/media/'.$file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        foreach($products as $index=>$p) DB::table('catalog_photo_tasks')->insert(['product_id'=>$p->id,'identity_hash'=>hash('sha256','task'.$p->id),'upload_id'=>$upload->id,
            'payload'=>json_encode(['id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku,'brand'=>$p->brand,'ean'=>'','ean_conflict'=>false]),'status'=>$index===0 ? 'ready' : 'review',
            'result'=>json_encode(['images'=>[['file'=>$file,'flags'=>[],'source'=>'Icecat','width'=>800,'height'=>800]]]),'created_at'=>now(),'updated_at'=>now()]);
        $outside=Product::create(['name'=>'Outside','sku'=>'OUT','brand'=>'HP','is_active'=>false]);
        $service=app(\App\Admin\CatalogRelease::class);
        $preview=$service->preview($upload);
        $this->assertCount(1,$preview['photos']);$this->assertCount(1,$preview['publish']);$this->assertCount(1,$preview['blocked']);
        $this->assertSame(1,$service->execute($upload,'photos',$preview['token'],$user->id));
        $this->assertSame(0,Product::where('is_active',true)->count());
        $this->get('/catalog-approved-image/'.$products[0]->id.'/0')->assertNotFound();
        $this->get('/admin/products/'.$products[0]->id.'/assigned-photo/0')->assertOk();
        $preview=$service->preview($upload);$this->assertCount(0,$preview['photos']);
        $this->assertSame(1,$service->execute($upload,'publish',$preview['token'],$user->id));
        $this->assertTrue($products[0]->fresh()->is_active);$this->assertFalse($products[1]->fresh()->is_active);$this->assertFalse($outside->fresh()->is_active);
        $this->get('/catalog-approved-image/'.$products[0]->id.'/0')->assertOk();
        $this->assertCount(1,\App\Support\ProductMediaQuality::images($products[0]->fresh()));
        $this->assertCount(0,$service->preview($upload)['publish']);
        $this->post('/admin/catalog-prices/'.$upload->id.'/release',['action'=>'publish','token'=>$preview['token'],'groups'=>['new','updated','unchanged']])->assertSessionHasErrors('release');
        $products[0]->update(['sku'=>'CHANGED']);
        $this->get('/catalog-approved-image/'.$products[0]->id.'/0')->assertNotFound();
    }

    public function test_release_groups_include_old_drafts_and_publish_only_selected_groups(): void
    {
        $user=$this->staff();
        $old=[$this->row(),[...$this->row(),'row'=>3,'sku'=>'OLD-2','name'=>'Old unchanged'],[...$this->row(),'row'=>4,'sku'=>'LIVE','name'=>'Already live']];
        app(CatalogDraftImport::class)->run($old,'altech-price',hash('sha256','old-release'),'test',true);
        Product::where('sku','LIVE')->update(['is_active'=>true]);
        $rows=$old;$rows[0]['price']='200.00';$rows[]=[...$this->row(),'row'=>5,'sku'=>'NEW','name'=>'New draft'];
        $summary=app(CatalogDraftImport::class)->run($rows,'altech-price',hash('sha256','group-release'),'test',true);
        $upload=$this->ready($rows);$upload->update(['status'=>'completed','source_profile'=>'altech-price','summary'=>$summary]);
        $service=app(\App\Admin\CatalogRelease::class);$preview=$service->preview($upload);
        $this->assertCount(3,$preview['publish']);$this->assertSame(1,$preview['published']);$this->assertSame(4,$preview['total']);
        $this->assertSame(['new','unchanged','updated'],collect($preview['publish'])->pluck('group')->sort()->values()->all());
        $url='/admin/catalog-prices/'.$upload->id.'/release';
        $this->post($url,['action'=>'publish','token'=>$preview['token'],'groups'=>['skipped']])->assertSessionHasErrors('groups.0');
        $this->post($url,['action'=>'publish','token'=>$preview['token'],'groups'=>['updated']])->assertRedirect();
        $this->assertTrue(Product::where('sku','TEST-1')->first()->is_active);
        $this->assertFalse(Product::where('sku','OLD-2')->first()->is_active);
        $this->assertFalse(Product::where('sku','NEW')->first()->is_active);
        $preview=$service->preview($upload);
        $this->assertSame(2,$service->execute($upload,'publish',$preview['token'],$user->id,['new','updated','unchanged']));
        $this->assertCount(0,$service->preview($upload)['publish']);
        $this->assertSame(4,Product::where('is_active',true)->count());
    }

    public function test_combined_release_assigns_photos_and_publishes_in_one_confirmation(): void
    {
        $user=$this->staff();$rows=[$this->row()];
        $summary=app(CatalogDraftImport::class)->run($rows,'altech-price',hash('sha256','combined'),'test',true);
        $upload=$this->ready($rows);$upload->update(['status'=>'completed','source_profile'=>'altech-price','summary'=>$summary]);
        $product=Product::firstOrFail();$file=str_repeat('b',64).'.png';
        Storage::disk('local')->put('catalog-media-queue/media/'.$file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        DB::table('catalog_photo_tasks')->insert(['product_id'=>$product->id,'identity_hash'=>hash('sha256','combined-task'),'upload_id'=>$upload->id,'payload'=>json_encode(['id'=>$product->id,'name'=>$product->name,'brand'=>$product->brand,'sku'=>$product->sku,'ean'=>'','ean_conflict'=>false]),'status'=>'ready','result'=>json_encode(['images'=>[['file'=>$file,'flags'=>[]]]]),'created_at'=>now(),'updated_at'=>now()]);
        $service=app(\App\Admin\CatalogRelease::class);$preview=$service->preview($upload);
        $url='/admin/catalog-prices/'.$upload->id.'/release';
        $this->post($url,['action'=>'release','token'=>str_repeat('0',64),'groups'=>['new']])->assertSessionHasErrors('release');
        $this->assertFalse($product->fresh()->is_active);$this->assertSame(0,DB::table('catalog_approved_media')->count());
        $this->post($url,['action'=>'release','token'=>$preview['token'],'groups'=>['new']])->assertRedirect();
        $this->assertTrue($product->fresh()->is_active);$this->assertSame(1,DB::table('catalog_approved_media')->count());
        $this->get('/catalog-approved-image/'.$product->id.'/0')->assertOk();
        $preview=$service->preview($upload);$this->assertCount(0,$preview['photos']);$this->assertCount(0,$preview['publish']);
    }

    private function staff(): User
    {
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user);
        return $user;
    }
    private function row(): array
    {
        return ['row' => 2, 'brand' => 'HP', 'sku' => 'TEST-1', 'name' => 'Ноутбук HP',
            'source_category' => 'IT', 'status' => 'include', 'rule' => 'laptop',
            'section' => 'Компьютеры и ноутбуки', 'subcategory' => 'Ноутбуки', 'errors' => [],
            'stock' => ['>50', '0', ''], 'price' => '100.00', 'currency' => 'RUB'];
    }
    private function ready(array $rows): CatalogPriceUpload
    {
        $workflow = app(CatalogPriceWorkflow::class);
        $hash = hash('sha256', 'fixture');
        $preview = $workflow->preview($rows, $hash);
        $json = json_encode($rows);
        $upload = CatalogPriceUpload::create(['id' => (string) Str::uuid(), 'user_id' => auth()->id(), 'name' => 'price.xlsx',
            'status' => 'ready', 'price_date'=>today()->toDateString(), 'file_hash' => $hash, 'staged_hash' => hash('sha256', $json),
            'fingerprint' => $preview['fingerprint'], 'summary' => $preview['summary']]);
        Storage::disk('local')->put($upload->directory().'/staged-products.json', $json);
        Storage::disk('local')->put($upload->directory().'/report.json', json_encode($preview['details']));
        return $upload;
    }
    public function test_workspace_compares_all_rows_without_applying_changes(): void
    {
        Queue::fake(); $this->staff(); $upload=$this->ready([$this->row()]);
        $rows=[];
        for($i=1;$i<=125;$i++) $rows[]=['row'=>$i,'sku'=>'SKU-'.$i,'name'=>'Position '.$i,'operation'=>'held','changes'=>[],'issues'=>[],'price'=>null,'stock'=>[]];
        Storage::disk('local')->put($upload->directory().'/report.json',json_encode($rows));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=compare&compare_page=3')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('comparison.total',125)->where('comparison.page',3)->has('details',25));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=compare&compare_search=SKU-125')->assertInertia(fn(Assert $p)=>$p->where('comparison.total',1)->where('details.0.sku','SKU-125'));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=photos')->assertInertia(fn(Assert $p)=>$p->where('photoQueue.total',0));
        $this->assertSame('ready',$upload->fresh()->status); Queue::assertNothingPushed();
    }

    public function test_workspace_photos_are_scoped_by_price_identity_not_task_upload(): void
    {
        $this->staff(); $row=$this->row(); $upload=$this->ready([$row]);
        $upload->update(['status'=>'completed','source_profile'=>'altech-price']);
        $included=Product::create(['name'=>'Included','sku'=>'TEST-1','is_active'=>false]);
        $outside=Product::create(['name'=>'Outside','sku'=>'OTHER','is_active'=>false]);
        DB::table('catalog_source_products')->insert(['source'=>'altech-price','identity_hash'=>CatalogDraftImport::identity($row['brand'],$row['sku'],$row['name']),'product_id'=>$included->id,'created_at'=>now(),'updated_at'=>now()]);
        foreach([$included,$outside] as $product) DB::table('catalog_photo_tasks')->insert(['product_id'=>$product->id,'identity_hash'=>hash('sha256',(string)$product->id),'upload_id'=>'older-upload','payload'=>json_encode(['name'=>$product->name,'sku'=>$product->sku]),'status'=>'preserved','created_at'=>now(),'updated_at'=>now()]);
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=photos')->assertOk()->assertInertia(fn(Assert $p)=>$p->where('photoQueue.total',1)->where('photoQueue.tasks.data.0.productId',$included->id));
        $this->get('/admin/catalog-prices/'.$upload->id.'?section=photos&search=Outside')->assertInertia(fn(Assert $p)=>$p->where('photoQueue.tasks.total',0));
        $this->assertFalse($included->fresh()->is_active);
    }

    public function test_upload_is_private_queued_and_owner_scoped(): void
    {
        Queue::fake();
        $this->staff();
        $this->get('/admin/catalog-prices')->assertOk()->assertInertia(fn (Assert $p) => $p->component('Admin/CatalogPrice'));
        $this->post('/admin/catalog-prices', ['file' => UploadedFile::fake()->create('price.xlsx', 1), 'price_date'=>today()->toDateString()])->assertRedirect();
        $upload = CatalogPriceUpload::firstOrFail();
        Storage::disk('local')->assertExists($upload->directory().'/source.xlsx');
        Queue::assertPushed(ProcessCatalogPrice::class, fn ($job) => $job->id === $upload->id && ! $job->apply);
        $this->assertDatabaseCount('products', 0);
        $this->staff();
        $this->get('/admin/catalog-prices/'.$upload->id)->assertNotFound();
        $this->post('/admin/catalog-prices/'.$upload->id.'/run')->assertNotFound();
        $this->get('/admin/catalog-prices/'.$upload->id.'/report')->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get('/admin/catalog-prices')->assertForbidden();
    }
    public function test_preview_rolls_back_every_catalog_write_and_apply_is_once_only(): void
    {
        Queue::fake();
        $this->staff();
        $upload = $this->ready([$this->row()]);
        foreach (['products', 'product_categories', 'catalog_import_mappings', 'catalog_import_batches', 'catalog_import_rows', 'catalog_source_products'] as $table) $this->assertDatabaseCount($table, 0);
        $this->assertSame(1, $upload->summary['created_drafts']);
        $this->get('/admin/catalog-prices/'.$upload->id)->assertOk();
        $this->post('/admin/catalog-prices/'.$upload->id.'/run')->assertRedirect();
        $this->post('/admin/catalog-prices/'.$upload->id.'/run')->assertConflict();
        $workflow = app(CatalogPriceWorkflow::class);
        $workflow->apply($upload);
        $this->assertSame('completed', $upload->fresh()->status);
        $this->assertDatabaseCount('products', 1);
        $this->assertFalse(Product::first()->is_active);
        $workflow->apply($upload);
        $this->assertDatabaseCount('catalog_import_batches', 1);
        $this->get('/admin/catalog-prices/'.$upload->id.'/report')->assertOk()->assertDownload('price-report.csv');
    }
    public function test_missing_product_loses_stock_only_and_return_restores_it(): void
    {
        $this->staff();
        $row=$this->row();
        app(CatalogDraftImport::class)->run([$row],'altech-price',hash('sha256','seed'),'test');
        $product=Product::firstOrFail(); $product->update(['is_active'=>true]);
        $category=$product->catalog_category_id;
        $other=[...$row,'row'=>3,'sku'=>'OTHER','name'=>'Ноутбук OTHER'];
        $upload=$this->ready([$other]);
        $this->assertSame('>50',$product->fresh()->availability);
        $upload->update(['status'=>'applying']); app(CatalogPriceWorkflow::class)->apply($upload);
        $product->refresh();
        $this->assertSame('0',$product->availability);
        $this->assertSame('100.00',$product->price);
        $this->assertTrue($product->is_active);
        $this->assertSame($category,$product->catalog_category_id);
        $this->assertFalse(\App\Commerce\ProductBridge::inStock($product));
        app(CatalogDraftImport::class)->run([$row],'altech-price',hash('sha256','return'),'test',true);
        $this->assertSame('>50',$product->fresh()->availability);
    }

    public function test_stale_preview_cannot_overwrite_newer_edits(): void
    {
        $this->staff();
        app(CatalogDraftImport::class)->run([$this->row()], 'altech-price', hash('sha256', 'seed'), 'test');
        $row = [...$this->row(), 'price' => '200'];
        $upload = $this->ready([$row]);
        Product::first()->update(['price' => 300]);
        $upload->update(['status' => 'applying']);
        app(CatalogPriceWorkflow::class)->apply($upload);
        $this->assertSame('stale', $upload->fresh()->status);
        $this->assertSame('300.00', Product::first()->price);
        $this->assertDatabaseCount('catalog_import_batches', 1);
    }
    public function test_changed_staging_is_rejected_and_failed_job_has_recoverable_status(): void
    {
        $this->staff();
        $upload = $this->ready([$this->row()]);
        Storage::disk('local')->put($upload->directory().'/staged-products.json', '[]');
        $upload->update(['status' => 'applying']);
        try {
            app(CatalogPriceWorkflow::class)->apply($upload);
            $this->fail('Changed file must be rejected');
        } catch (\RuntimeException $e) {
            (new ProcessCatalogPrice($upload->id, true))->failed($e);
        }
        $this->assertSame('failed', $upload->fresh()->status);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('catalog_import_batches', 0);
        Queue::fake();
        $this->post('/admin/catalog-prices/'.$upload->id.'/refresh')->assertRedirect();
        $this->assertSame('queued', $upload->fresh()->status);
    }
    public function test_wrong_extension_is_rejected_without_queueing(): void
    {
        Queue::fake();
        $this->staff();
        $this->post('/admin/catalog-prices', ['file' => UploadedFile::fake()->create('price.csv', 1)])->assertSessionHasErrors('file');
        Queue::assertNothingPushed();
        $this->assertDatabaseCount('catalog_price_uploads', 0);
    }
}

