<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Hash,Password};
use Tests\TestCase;

class LocalPasswordResetTest extends TestCase
{
    use RefreshDatabase;
    public function test_local_token_is_required_and_single_use(): void
    {
        $this->withoutVite();config(['inertia.ssr.enabled'=>false]);
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $user=User::factory()->create(['is_admin'=>true]);$token=Password::broker()->createToken($user);
        $url='/admin/local-reset-password/'.$token.'?email='.urlencode($user->email);
        $this->get($url)->assertNotFound();$this->app['env']='local';
        $before=$user->password;$this->get($url)->assertOk()->assertHeader('Referrer-Policy','no-referrer');
        $this->assertSame($before,$user->fresh()->password);
        $data=['email'=>$user->email,'token'=>$token,'password'=>'Test-Only-Password-123','password_confirmation'=>'Test-Only-Password-123'];
        $this->post('/admin/local-reset-password',[...$data,'token'=>'invalid'])->assertSessionHasErrors('password');
        $this->assertSame($before,$user->fresh()->password);
        $this->post('/admin/local-reset-password',$data)->assertRedirect('/admin/login');
        $this->assertTrue(Hash::check($data['password'],$user->fresh()->password));
        $this->get($url)->assertNotFound();
    }
}
