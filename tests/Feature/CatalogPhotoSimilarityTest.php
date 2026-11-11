<?php
namespace Tests\Feature;

use App\Models\{Product,User};
use App\Support\{PhotoPerceptualHash,CatalogPhotoSimilarity,ApprovedProductMedia};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Tests\TestCase;

class CatalogPhotoSimilarityTest extends TestCase
{
    use RefreshDatabase;
    private function photo(int $width=800,int $quality=95,int $variant=0): array
    {
        $im=imagecreatetruecolor($width,(int)($width*0.75));
        $white=imagecolorallocate($im,255,255,255);$dark=imagecolorallocate($im,25,40,65);
        imagefill($im,0,0,$white);
        if (!$variant) {
            imagefilledrectangle($im,(int)($width*.15),(int)($width*.12),(int)($width*.8),(int)($width*.5),$dark);
            imagefilledrectangle($im,(int)($width*.4),(int)($width*.5),(int)($width*.55),(int)($width*.65),$dark);
        } else {
            for($i=0;$i<9;$i++) imagefilledellipse($im,(int)($width*(.1+($i%3)*.3)),(int)($width*(.1+intdiv($i,3)*.23)),(int)($width*.12),(int)($width*.1),$dark);
        }
        ob_start();imagejpeg($im,null,$quality);$bytes=ob_get_clean();imagedestroy($im);
        $file=hash('sha256',$bytes).'.jpg';Storage::disk('local')->put('catalog-media-queue/media/'.$file,$bytes);
        return ['file'=>$file,'source'=>'Test','width'=>$width,'height'=>(int)($width*.75),'flags'=>[]];
    }
    private function task(array $image,string $sku): object
    {
        $p=Product::create(['name'=>'Display '.$sku,'sku'=>$sku,'brand'=>'Test','is_active'=>false]);
        $payload=json_encode(['name'=>$p->name,'sku'=>$p->sku,'brand'=>$p->brand]);$result=json_encode(['images'=>[$image]]);
        $id=DB::table('catalog_photo_tasks')->insertGetId(['product_id'=>$p->id,'identity_hash'=>hash('sha256',$sku),'upload_id'=>'test','payload'=>$payload,'result'=>$result,'status'=>'review','created_at'=>now(),'updated_at'=>now()]);
        return DB::table('catalog_photo_tasks')->find($id);
    }
    public function test_hash_matches_resized_recompressed_images_but_not_different_shapes(): void
    {
        Storage::fake('local');$service=new CatalogPhotoSimilarity;
        $a=$this->photo();$b=$this->photo(640,60);$c=$this->photo(800,95,1);
        $ha=$service->fingerprint($a['file']);$hb=$service->fingerprint($b['file']);$hc=$service->fingerprint($c['file']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/',$ha);
        $this->assertNotSame($a['file'],$b['file']);
        $this->assertLessThanOrEqual(6,PhotoPerceptualHash::distance($ha,$hb));
        $this->assertGreaterThan(6,PhotoPerceptualHash::distance($ha,$hc));
        $this->assertSame($ha,(new CatalogPhotoSimilarity)->fingerprint($a['file']));
    }
    public function test_matches_are_warnings_and_a_matching_existing_photo_can_be_selected(): void
    {
        Storage::fake('local');$a=$this->photo();$a['flags']=['shared_across_models'];$b=$this->photo(640,60);
        $target=$this->task($a,'TARGET');$source=$this->task($b,'SOURCE');
        $url='/admin/catalog-photo-queue/'.$target->id;
        $this->get($url.'/matches/0')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin'=>true]));
        $response=$this->getJson($url.'/matches/0')->assertOk()->assertJsonPath('total',1)->assertJsonPath('matches.0.productId',$source->product_id)->assertJsonPath('matches.0.exact',false);
        $this->assertTrue(ApprovedProductMedia::valid($a));
        $data=['decision'=>'accept_match','index'=>0,'token'=>hash('sha256',$target->payload.$target->result.'review'),'matchId'=>$response->json('matches.0.id')];
        $this->postJson($url.'/review',[...$data,'matchId'=>str_repeat('0',64)])->assertUnprocessable();
        $this->postJson($url.'/review',$data)->assertOk()->assertJsonPath('status','preserved');
        $result=json_decode(DB::table('catalog_photo_tasks')->find($target->id)->result,true);
        $this->assertSame($b['file'],$result['images'][0]['file']);
        $this->assertSame($source->product_id,$result['selected_match']['product_id']);
        $this->assertFalse(Product::find($target->product_id)->is_active);
        $this->assertDatabaseCount('catalog_approved_media',1);
        $this->postJson($url.'/review',$data)->assertUnprocessable();
    }
    public function test_current_duplicate_can_be_accepted_but_hard_flags_still_block(): void
    {
        Storage::fake('local');$this->actingAs(User::factory()->create(['is_admin'=>true]));
        foreach([['shared_across_models'],['shared_across_models','small']] as $i=>$flags) {
            $image=$this->photo();$image['flags']=$flags;$task=$this->task($image,'FLAGS'.$i);
            $response=$this->postJson('/admin/catalog-photo-queue/'.$task->id.'/review',['decision'=>'accept','index'=>0,'token'=>hash('sha256',$task->payload.$task->result.'review')]);
            if ($i===0) $response->assertOk()->assertJsonPath('status','preserved');else $response->assertUnprocessable();
        }
    }
    public function test_stale_or_unrelated_source_cannot_be_selected(): void
    {
        Storage::fake('local');$this->actingAs(User::factory()->create(['is_admin'=>true]));
        $target=$this->task($this->photo(),'TARGET');$source=$this->task($this->photo(640,60),'SOURCE');
        $url='/admin/catalog-photo-queue/'.$target->id;
        $match=$this->getJson($url.'/matches/0')->json('matches.0.id');
        DB::table('catalog_photo_tasks')->where('id',$source->id)->update(['status'=>'rejected']);
        $this->postJson($url.'/review',['decision'=>'accept_match','index'=>0,'token'=>hash('sha256',$target->payload.$target->result.'review'),'matchId'=>$match])->assertUnprocessable();
        $this->assertDatabaseHas('catalog_photo_tasks',['id'=>$target->id,'status'=>'review']);
    }
}
