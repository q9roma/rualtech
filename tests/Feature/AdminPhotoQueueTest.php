<?php
namespace Tests\Feature;
use App\Models\{User,Product};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminPhotoQueueTest extends TestCase
{
    use RefreshDatabase;
    public function test_upload_recovers_failed_task_and_requires_confirmation(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $p=Product::create(['name'=>'Monitor','brand'=>'LG','sku'=>'FAILED-UPLOAD','is_active'=>false]);
        $payload=json_encode(['name'=>$p->name,'brand'=>$p->brand,'sku'=>$p->sku]);
        $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$p->id,'identity_hash'=>hash('sha256','failed-upload'),'upload_id'=>'test','payload'=>$payload,'status'=>'failed','result'=>null,'error'=>'Download failed','created_at'=>now(),'updated_at'=>now()]);
        $url='/admin/catalog-photo-queue/'.$id.'/review';
        $token=hash('sha256',$payload.'failed');
        $this->postJson($url,['decision'=>'accept_all','token'=>$token])->assertUnprocessable();
        $this->postJson($url,['decision'=>'upload','token'=>str_repeat('0',64),'photo'=>\Illuminate\Http\UploadedFile::fake()->image('photo.jpg',800,600)])->assertUnprocessable();
        $response=$this->postJson($url,['decision'=>'upload','token'=>$token,'photo'=>\Illuminate\Http\UploadedFile::fake()->image('photo.jpg',800,600)])
            ->assertOk()->assertJsonPath('status','review')->assertJsonCount(1,'images');
        $this->assertDatabaseHas('catalog_photo_tasks',['id'=>$id,'status'=>'review','lease_token'=>null,'available_at'=>null]);
        $this->get('/admin/catalog-photo-queue/'.$id.'/image/0')->assertOk();
        $this->postJson($url,['decision'=>'accept','index'=>0,'token'=>$response->json('reviewToken')])->assertOk()->assertJsonPath('status','preserved');
        $this->assertDatabaseCount('catalog_approved_media',1);
    }
    public function test_manual_upload_stays_in_review_and_can_be_previewed(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $p=Product::create(['name'=>'LG Monitor','brand'=>'LG','sku'=>'UPLOAD','is_active'=>false]);
        $payload=json_encode(['name'=>$p->name,'brand'=>$p->brand,'sku'=>$p->sku]);
        $result=json_encode(['images'=>[]]);
        $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$p->id,'identity_hash'=>hash('sha256','upload'),'upload_id'=>'test','payload'=>$payload,'status'=>'review','result'=>$result,'created_at'=>now(),'updated_at'=>now()]);
        $data=['decision'=>'upload','token'=>hash('sha256',$payload.$result.'review')];
        $url='/admin/catalog-photo-queue/'.$id.'/review';
        $this->post($url,[...$data,'photo'=>\Illuminate\Http\UploadedFile::fake()->create('bad.txt',1,'text/plain')])->assertSessionHasErrors('photo');
        $response=$this->postJson($url,[...$data,'photo'=>\Illuminate\Http\UploadedFile::fake()->image('photo.jpg',800,600)])
            ->assertOk()->assertJsonPath('status','review')->assertJsonCount(1,'images')
            ->assertJsonPath('images.0.source','Загружено вручную')->assertJsonPath('images.0.width',800);
        $task=DB::table('catalog_photo_tasks')->find($id);
        $this->assertSame('review',$task->status);
        $this->assertCount(1,json_decode($task->result,true)['images']);
        $response->assertJsonPath('reviewToken',hash('sha256',$payload.$task->result.'review'));
        $this->get('/admin/catalog-photo-queue/'.$id.'/image/0')->assertOk();
        $this->post($url,[...$data,'photo'=>\Illuminate\Http\UploadedFile::fake()->image('photo.jpg',800,600)])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('catalog_approved_media',0);
        $this->assertFalse($p->fresh()->is_active);
    }
    public function test_category_and_subcategory_filters_scope_tasks_and_counts(): void
    {
        $this->withoutVite(); Storage::fake('local'); config(['inertia.ssr.enabled'=>false]);
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $root=\App\Models\ProductCategory::create(['name'=>'Computers']);
        $child=\App\Models\ProductCategory::create(['name'=>'Laptops','parent_id'=>$root->id]);
        $other=\App\Models\ProductCategory::create(['name'=>'Displays']);
        foreach ([$root->id,$child->id,$other->id,null] as $i=>$category) {
            $product=Product::create(['name'=>'Item '.$i,'sku'=>'FILTER-'.$i,'catalog_category_id'=>$category,'is_active'=>false]);
            DB::table('catalog_photo_tasks')->insert(['product_id'=>$product->id,'identity_hash'=>hash('sha256','filter'.$i),'upload_id'=>'test','payload'=>'{}','status'=>'queued','created_at'=>now(),'updated_at'=>now()]);
        }
        $this->get('/admin/catalog-photo-queue?category='.$root->id)->assertOk()->assertInertia(fn(Assert $page)=>$page->where('tasks.total',2)->where('total',2));
        $this->get('/admin/catalog-photo-queue?category='.$root->id.'&subcategory='.$child->id.'&search=FILTER-1')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('tasks.total',1)->where('total',1)->where('filters.subcategory',(string)$child->id));
        $this->get('/admin/catalog-photo-queue?category=0')->assertOk()->assertInertia(fn(Assert $page)=>$page->where('tasks.total',1));
        $this->get('/admin/catalog-photo-queue?category='.$other->id.'&subcategory='.$child->id)->assertStatus(422);
    }
    public function test_admin_can_filter_queue_and_see_issues_but_guests_cannot(): void
    {
        $this->withoutVite();Storage::fake('local');config(['inertia.ssr.enabled'=>false]);
        $this->get('/admin/catalog-photo-queue')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>false]));$this->get('/admin/catalog-photo-queue')->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $p=Product::create(['name'=>'Monitor LG','brand'=>'LG','sku'=>'TEST-M1','is_active'=>false]);
        foreach(['review','preserved','queued','superseded'] as $status) DB::table('catalog_photo_tasks')->insert([
            'product_id'=>$p->id,'identity_hash'=>hash('sha256',$status),'upload_id'=>'test','payload'=>json_encode(['name'=>$p->name,'sku'=>$p->sku,'brand'=>$p->brand,'ean'=>'123']),
            'status'=>$status,'result'=>json_encode(['review_reason'=>'Identity conflict','identity_evidence'=>['brand'=>'LG','mpn'=>'WRONG','gtins'=>['456'],'verdict'=>'conflict']]),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->get('/admin/catalog-photo-queue?status=review&search=TEST-M1')->assertOk()->assertInertia(fn(Assert $page)=>$page->component('Admin/PhotoQueue')->where('total',3)->where('completed',2)->where('tasks.total',1)->where('tasks.data.0.status','review')->where('tasks.data.0.identity.mpn','WRONG'));
        $this->get('/admin/catalog-photo-queue?search=missing')->assertInertia(fn(Assert $page)=>$page->where('tasks.total',0));
        $this->get('/admin/catalog-photo-queue')->assertInertia(fn(Assert $page)=>$page->where('tasks.total',3));
        $this->assertFalse($p->fresh()->is_active);
    }
    public function test_candidate_image_requires_admin_and_exact_task_file(): void
    {
        Storage::fake('local');$file=str_repeat('a',64).'.png';
        $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>999,'identity_hash'=>hash('sha256','image'),'upload_id'=>'test','payload'=>'{}','status'=>'review','result'=>json_encode(['images'=>[['file'=>$file]]]),'created_at'=>now(),'updated_at'=>now()]);
        Storage::disk('local')->put('catalog-media-queue/media/'.$file,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8ZsAAAAASUVORK5CYII='));
        $url='/admin/catalog-photo-queue/'.$id.'/image/0';$this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));$this->get($url)->assertOk()->assertHeader('Cache-Control','no-store, private');
        $this->get('/admin/catalog-photo-queue/'.$id.'/image/1')->assertNotFound();
        DB::table('catalog_photo_tasks')->where('id',$id)->update(['result'=>json_encode(['images'=>[['file'=>'../secret.txt']]])]);$this->get($url)->assertNotFound();
    }
    public function test_review_decisions_assign_confirmed_media_without_publishing_and_reject_stale_tokens(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $p=Product::create(['name'=>'LG Monitor','brand'=>'LG','sku'=>'M1','is_active'=>false]);
        $payload=json_encode(['name'=>$p->name,'brand'=>$p->brand,'sku'=>$p->sku]);
        $file=str_repeat('b',64).'.png';
        Storage::disk('local')->put('catalog-media-queue/media/'.$file,'test-image');
        foreach (['accept','reject'] as $decision) {
            $result=json_encode(['images'=>[['file'=>$file,'flags'=>[]]]]);
            $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$p->id,'identity_hash'=>hash('sha256',$decision),'upload_id'=>'test','payload'=>$payload,'status'=>'review','result'=>$result,'created_at'=>now(),'updated_at'=>now()]);
            $url='/admin/catalog-photo-queue/'.$id.'/review';
            $data=['decision'=>$decision,'index'=>0,'token'=>hash('sha256',$payload.$result.'review')];
            $this->post($url,[...$data,'token'=>str_repeat('0',64)])->assertSessionHasErrors('review');
            $this->post($url,$data)->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('catalog_photo_tasks',['id'=>$id,'status'=>$decision==='accept' ? 'preserved' : 'rejected']);
            $this->post($url,$data)->assertSessionHasErrors('review');
        }
        $this->assertFalse($p->fresh()->is_active);
        $this->assertDatabaseCount('catalog_approved_media',1);
    }
    public function test_bulk_accept_validates_every_photo_and_single_reject_preserves_remaining_candidates(): void
    {
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $p=Product::create(['name'=>'Display','brand'=>'Philips','sku'=>'MULTI','is_active'=>false]);
        $payload=json_encode(['name'=>$p->name,'brand'=>$p->brand,'sku'=>$p->sku]);
        $images=[];
        foreach (['a','b','c'] as $i=>$char) {
            $file=str_repeat($char,64).'.jpg';
            Storage::disk('local')->put('catalog-media-queue/media/'.$file,'image');
            $images[]=['file'=>$file,'flags'=>$i===1 ? ['small'] : []];
        }
        $result=json_encode(['images'=>$images]);
        $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$p->id,'identity_hash'=>hash('sha256','multi'),'upload_id'=>'test','payload'=>$payload,'status'=>'review','result'=>$result,'created_at'=>now(),'updated_at'=>now()]);
        $url='/admin/catalog-photo-queue/'.$id.'/review';
        $token=hash('sha256',$payload.$result.'review');
        $this->post($url,['decision'=>'accept_all','token'=>$token])->assertSessionHasErrors('review');
        $this->assertSame($result,DB::table('catalog_photo_tasks')->find($id)->result);
        $this->post($url,['decision'=>'reject','index'=>99,'token'=>$token])->assertSessionHasErrors('review');
        $this->postJson($url,['decision'=>'reject','index'=>1,'token'=>$token])->assertOk()->assertJsonPath('status','review')->assertJsonCount(2,'images');
        $row=DB::table('catalog_photo_tasks')->find($id);
        $this->assertSame('review',$row->status);
        $remaining=json_decode($row->result,true);
        $this->assertSame([$images[0],$images[2]],$remaining['images']);
        $this->assertSame([$images[1]],$remaining['review_rejected_images']);
        $this->post($url,['decision'=>'accept_all','token'=>$token])->assertSessionHasErrors('review');
        $this->postJson($url,['decision'=>'accept_all','token'=>hash('sha256',$payload.$row->result.'review')])->assertOk()->assertJsonPath('status','preserved')->assertJsonCount(2,'images');
        $row=DB::table('catalog_photo_tasks')->find($id);
        $this->assertSame('preserved',$row->status);
        $this->assertSame([$images[0],$images[2]],json_decode($row->result,true)['images']);
        $this->assertDatabaseCount('catalog_approved_media',1);
        $this->assertFalse($p->fresh()->is_active);
    }
}
