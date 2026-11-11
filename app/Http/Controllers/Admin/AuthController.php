<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function form(Request $request)
    {
        return $request->user()?->is_admin ? redirect('/admin') : Inertia::render('Admin/Login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string', 'remember' => 'boolean']);
        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password'], 'is_admin' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Неверные данные или нет доступа к управлению.']);
        }
        $request->session()->regenerate();
        return redirect()->intended('/admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/admin/login');
    }
}
