<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Hash, Password};
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;

/** Local recovery form; tokens are issued only by a trusted operator, never by a public endpoint. */
class LocalPasswordResetController extends Controller
{
    public function form(Request $request, string $token)
    {
        abort_unless(app()->environment('local'),404);
        $user=User::where('email',$request->query('email'))->where('is_admin',true)->first();
        abort_unless($user && Password::broker()->tokenExists($user,$token),404);
        return Inertia::render('Admin/ResetPassword',['email'=>$user->email,'token'=>$token])->toResponse($request)
            ->header('Cache-Control','private, no-store')->header('Referrer-Policy','no-referrer')->header('X-Robots-Tag','noindex, nofollow');
    }
    public function reset(Request $request)
    {
        abort_unless(app()->environment('local'),404);
        $data=$request->validate(['email'=>'required|email','token'=>'required|string','password'=>['required','confirmed','max:128',PasswordRule::min(12)]]);
        $status=Password::broker()->reset([...$data,'is_admin'=>true],function(User $user,string $password) {
            $user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60)])->save();
            event(new \Illuminate\Auth\Events\PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) return back()->withErrors(['password'=>'Ссылка недействительна или уже использована. Нужна новая ссылка восстановления.']);
        return redirect('/admin/login')->with('success','Пароль сохранён. Войдите с новым паролем.');
    }
}
