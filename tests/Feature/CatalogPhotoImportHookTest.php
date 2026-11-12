<?php
namespace Tests\Feature;

use App\Admin\CatalogPriceWorkflow;
use App\Models\{CatalogPriceUpload,User};
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class CatalogPhotoImportHookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commits are required to exercise afterCommit; no outer test transaction.
        $this->assertSame('testing', app()->environment());
        $connection = DB::getDefaultConnection();
        $database = DB::connection()->getDatabaseName();
        $this->assertTrue(
            ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'mysql' && $database === 'altech_test'),
            'Real-commit tests may reset only the explicitly designated test database.'
        );
        $this->artisan('migrate:fresh', ['--force'=>true]);
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }
    private function ready(): CatalogPriceUpload
    {
        Storage::fake('local');
        $row=['row'=>2,'brand'=>'HP','sku'=>'HOOK-1','name'=>'Ноутбук HP','source_category'=>'IT','status'=>'include','rule'=>'laptop','section'=>'Компьютеры и ноутбуки','subcategory'=>'Ноутбуки','errors'=>[],'stock'=>['5','0',''],'price'=>'100.00','currency'=>'RUB'];
        $preview=app(CatalogPriceWorkflow::class)->preview([$row],hash('sha256','hook'));
        $this->assertDatabaseCount('catalog_photo_tasks',0);
        $json=json_encode([$row]); $user=User::factory()->create();
        $u=CatalogPriceUpload::create(['id'=>(string)Str::uuid(),'user_id'=>$user->id,'name'=>'test.xlsx','price_date'=>today()->toDateString(),'status'=>'applying','source_profile'=>'altech-price','file_hash'=>hash('sha256','hook'),'staged_hash'=>hash('sha256',$json),'fingerprint'=>$preview['fingerprint'],'summary'=>$preview['summary']]);
        Storage::disk('local')->put($u->directory().'/staged-products.json',$json);
        return $u;
    }
    public function test_real_commit_plans_once_and_preview_does_not_plan(): void
    {
        $u=$this->ready();$workflow=app(CatalogPriceWorkflow::class);$workflow->apply($u);$workflow->apply($u);
        $this->assertSame('completed',$u->fresh()->status);
        $this->assertDatabaseCount('catalog_photo_tasks',1);
        $this->assertDatabaseHas('catalog_photo_tasks',['status'=>'queued']);
        $this->assertDatabaseHas('products',['sku'=>'HOOK-1','is_active'=>false]);
    }
    public function test_planning_failure_does_not_rollback_prices_and_reconciliation_recovers(): void
    {
        $u=$this->ready();
        $this->mock(\App\Support\CatalogPhotoQueue::class,fn($mock)=>$mock->shouldReceive('plan')->once()->andThrow(new \RuntimeException('queue temporarily unavailable')));
        app(CatalogPriceWorkflow::class)->apply($u);
        $this->assertSame('completed',$u->fresh()->status);
        $this->assertDatabaseHas('products',['sku'=>'HOOK-1','price'=>100]);
        (new \App\Support\CatalogPhotoQueue)->plan();
        $this->assertDatabaseCount('catalog_photo_tasks',1);
    }
    public function test_deployed_import_plans_photos_when_explicitly_enabled(): void
    {
        $u = $this->ready();
        $this->app->instance('env', 'production');
        config(['catalog-import.photos_enabled' => true]);
        app(CatalogPriceWorkflow::class)->apply($u);
        $this->assertSame('completed', $u->fresh()->status);
        $this->assertDatabaseHas('catalog_photo_tasks', ['status' => 'queued']);
    }
}
