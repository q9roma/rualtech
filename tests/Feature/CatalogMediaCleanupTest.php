<?php
namespace Tests\Feature;

use App\Support\{CatalogMediaCleanup, CatalogMediaStorage};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class CatalogMediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_approved_and_historical_images_survive_but_old_rejection_expires(): void
    {
        Storage::fake('local'); $disk=Storage::disk('local');
        foreach (['active','approved','history','rejected'] as $index=>$name) {
            $file=hash('sha256',$name).'.jpg';
            $disk->put(CatalogMediaCleanup::DIRECTORY.'/'.$file,$name);
            touch($disk->path(CatalogMediaCleanup::DIRECTORY.'/'.$file),now()->subDays(60)->timestamp);
            DB::table('catalog_photo_tasks')->insert(['id'=>$index+1,'product_id'=>$index+1,'identity_hash'=>hash('sha256',$name),'upload_id'=>'test','payload'=>'{}','status'=>$name==='active' ? 'review' : 'rejected','result'=>json_encode(['images'=>[['file'=>$file]]]),'created_at'=>now()->subDays(60),'updated_at'=>now()->subDays(60)]);
        }
        DB::table('catalog_approved_media')->insert(['product_id'=>2,'task_id'=>2,'user_id'=>1,'identity'=>'{}','images'=>json_encode([['file'=>hash('sha256','approved').'.jpg']])]);
        DB::table('catalog_product_events')->insert(['product_id'=>3,'kind'=>'photos_assigned','changes'=>'{}','context'=>'{"task_id":3}','created_at'=>now()]);
        $service=new CatalogMediaCleanup;
        $this->assertSame(1,$service->run()['unreferenced_files']);
        $this->travel(31)->days();
        $this->assertSame(1,$service->run(true)['deleted']);
        foreach (['active','approved','history'] as $name) $disk->assertExists(CatalogMediaCleanup::DIRECTORY.'/'.hash('sha256',$name).'.jpg');
        $disk->assertMissing(CatalogMediaCleanup::DIRECTORY.'/'.hash('sha256','rejected').'.jpg');
    }

    public function test_cleanup_requires_observed_grace_and_preserves_manifest_references(): void
    {
        Storage::fake('local'); $disk=Storage::disk('local');
        $file=hash('sha256','orphan').'.jpg'; $path=CatalogMediaCleanup::DIRECTORY.'/'.$file;
        $disk->put($path,'orphan'); touch($disk->path($path),now()->subDays(60)->timestamp);
        $service=new CatalogMediaCleanup;
        $this->assertSame(0,$service->run(true)['deleted']);
        $this->travel(31)->days();
        $disk->put('catalog-automation/photo-quality/approved-media.json',json_encode([$file=>true]));
        $this->assertSame(0,$service->run(true)['deleted']);
        $disk->put('catalog-automation/photo-quality/approved-media.json','{}');
        $this->assertSame(0,$service->run(true)['deleted']); // Reference reset the clock.
        $this->travel(31)->days();
        $this->assertSame(1,$service->run(true)['deleted']);
        $disk->assertMissing($path);
    }

    public function test_consolidation_moves_unique_files_and_retains_duplicate_grace_copy(): void
    {
        Storage::fake('local'); $disk=Storage::disk('local');
        $file=hash('sha256','photo').'.jpg';
        $disk->put(CatalogMediaStorage::LEGACY.'/'.$file,'photo');
        $this->assertSame(CatalogMediaStorage::LEGACY.'/'.$file,CatalogMediaStorage::path($file));
        $this->assertSame(['moved'=>1,'duplicates'=>0],CatalogMediaStorage::consolidate());
        $this->assertSame(CatalogMediaCleanup::DIRECTORY.'/'.$file,CatalogMediaStorage::path($file));
        $disk->put(CatalogMediaStorage::LEGACY.'/'.$file,'photo');
        $this->assertSame(['moved'=>0,'duplicates'=>1],CatalogMediaStorage::consolidate());
        $service=new CatalogMediaCleanup;
        $disk->put('catalog-automation/photo-quality/approved-media.json',json_encode([$file=>true]));
        $this->assertSame(0,$service->run(true)['deleted']);
        $this->travel(31)->days();
        $this->assertSame(1,$service->run(true)['deleted']);
        $disk->assertExists(CatalogMediaCleanup::DIRECTORY.'/'.$file);
        $disk->assertMissing(CatalogMediaStorage::LEGACY.'/'.$file);
    }

    public function test_invalid_manifest_prevents_deletion(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('catalog-automation/photo-quality/review.json','broken');
        $this->expectException(\JsonException::class);
        (new CatalogMediaCleanup)->run(true);
    }
}
