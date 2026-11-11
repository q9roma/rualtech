<?php
namespace Tests\Feature;

use App\Support\CatalogPhotoQueue;
use Illuminate\Support\Facades\{Cache,Storage};
use Tests\TestCase;

class CatalogPhotosContinuousTest extends TestCase
{
    public function test_continuous_worker_processes_more_than_three_tasks_without_batch_pause(): void
    {
        Storage::fake('local'); Cache::store('array')->flush();
        $times=[];
        $this->mock(CatalogPhotoQueue::class,function($mock) use (&$times) {
            $mock->shouldReceive('plan')->once()->andReturn([]);
            $mock->shouldReceive('claim')->andReturn((object)['id'=>1],(object)['id'=>2],(object)['id'=>3],(object)['id'=>4],null);
            $mock->shouldReceive('run')->times(4)->andReturnUsing(function() use (&$times){$times[]=microtime(true);});
            $mock->shouldReceive('report')->andReturn(['generated_at'=>now()->toIso8601String()]);
        });
        $this->artisan('catalog:photos-work',['--continuous'=>true,'--max-time'=>1])->assertSuccessful();
        $this->assertCount(4,$times);
        $this->assertLessThan(1,$times[3]-$times[0]);
        $lock=Cache::lock('catalog-photos-daemon',10);
        $this->assertTrue($lock->get()); $lock->release();
    }
}
