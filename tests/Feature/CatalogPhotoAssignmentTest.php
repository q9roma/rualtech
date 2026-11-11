<?php
namespace Tests\Feature;

use App\Models\Product;
use App\Support\CatalogPhotoAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CatalogPhotoAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_is_automatic_idempotent_and_does_not_publish(): void
    {
        Storage::fake('local');
        foreach (['confirmed', 'review', 'manual', 'stale'] as $case) {
            $product = Product::create(['name'=>$case, 'brand'=>'Brand', 'sku'=>$case, 'is_active'=>false, 'photos'=>$case==='manual' ? [] : null]);
            $file=hash('sha256',$case).'.jpg';
            Storage::disk('local')->put('catalog-media-queue/media/'.$file, 'image');
            $id=DB::table('catalog_photo_tasks')->insertGetId([
                'product_id'=>$product->id, 'upload_id'=>'legacy', 'identity_hash'=>hash('sha256',$case), 'status'=>'ready',
                'payload'=>json_encode(['name'=>$case==='stale' ? 'old' : $case, 'brand'=>'Brand', 'sku'=>$case]),
                'result'=>json_encode(['images'=>[['file'=>$file,'flags'=>[],'identity'=>$case==='review' ? 'review' : 'confirmed']]]),
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
            $service=new CatalogPhotoAssignment;
            $this->assertSame($case==='confirmed', $service->assign($id));
            $this->assertFalse($service->assign($id));
            $this->assertFalse((bool)$product->fresh()->is_active);
        }
        $this->assertDatabaseCount('catalog_approved_media',1);
        $this->assertDatabaseHas('catalog_approved_media',['origin'=>'automatic','user_id'=>null]);
        $this->assertSame(1,DB::table('catalog_product_events')->where('kind','photos_assigned')->count());
    }
}
