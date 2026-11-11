<?php
namespace Tests\Feature;
use App\Admin\CatalogPriceComposition;
use App\Models\{CatalogPriceUpload,User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPriceCompositionTest extends TestCase
{
    use RefreshDatabase;
    public function test_membership_uses_previous_applied_source_file_and_ignores_stock(): void
    {
        Storage::fake('local');
        $user=User::factory()->create();
        $before=[['row'=>2,'supplier_id'=>'A','stock'=>['0']],['row'=>3,'supplier_id'=>'B','stock'=>['5']]];
        $json=json_encode($before);
        $old=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'old.xlsx','source_profile'=>'supplier-price-32','status'=>'completed','file_hash'=>str_repeat('a',64),'staged_hash'=>hash('sha256',$json),'created_at'=>now()->subDay()]);
        Storage::disk('local')->put($old->directory().'/staged-products.json',$json);
        $current=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'new.xlsx','source_profile'=>'supplier-price-32','status'=>'ready','file_hash'=>str_repeat('b',64)]);
        $result=CatalogPriceComposition::compare($current,[['row'=>2,'supplier_id'=>'A','stock'=>['500']],['row'=>4,'supplier_id'=>'C','stock'=>['0']]]);
        $this->assertSame([4],$result['appeared']);
        $this->assertSame('B',$result['missing'][0]['supplier_id']);
        $this->assertSame($old->id,$result['baseline']['id']);
        Storage::disk('local')->put($old->directory().'/staged-products.json','[]');
        $this->assertNull(CatalogPriceComposition::compare($current,[])['baseline']);
    }
}
