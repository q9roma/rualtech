<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Commerce\OrderWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Lunar\Models\Order;

class AccountController extends Controller
{
    public function form(Request $request)
    {
        if ($request->user()) return redirect('/account');
        $register = $request->routeIs('register');
        return Inertia::render('account/auth', ['title' => $register ? 'Регистрация' : 'Вход',
            'canonicalUrl' => url($register ? '/register' : '/login'), 'register' => $register]);
    }

    public function register(Request $request)
    {
        abort_if($request->user(), 409);
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'max:128', Password::min(12)]]);
        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();
        return redirect('/account');
    }

    public function login(Request $request)
    {
        abort_if($request->user(), 409);
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:128']]);
        $data['email'] = mb_strtolower(trim($data['email']));
        if (! Auth::attempt($data)) throw ValidationException::withMessages(['email' => 'Неверный email или пароль.']);
        $request->session()->regenerate();
        return redirect($request->input('return_to') === '/checkout' ? '/checkout' : '/account');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    private function summary(Order $order): array
    {
        return ['id' => $order->id, 'reference' => $order->reference, 'status' => OrderWorkflow::LABELS[$order->status] ?? 'Обрабатывается',
            'total' => $order->currency_code === 'RUB' ? \App\Commerce\ShoppingCart::money($order->total->value) : $order->total->formatted, 'date' => $order->placed_at->format('d.m.Y H:i'), 'url' => route('orders.show', $order->id)];
    }

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)->whereNotNull('placed_at')->with('currency')->orderByDesc('id')->paginate(15)
            ->through(fn ($order) => $this->summary($order));
        $guests = Order::whereIn('id', $request->session()->get('commerce.orders', []))->whereNull('user_id')->whereNotNull('placed_at')
            ->with('currency')->get()->map(fn ($order) => $this->summary($order));
        return Inertia::render('account/index', ['title' => 'Мои заказы', 'canonicalUrl' => url('/account'),
            'buyer' => $request->user()->only(['name', 'email']), 'orders' => $orders, 'guestOrders' => $guests]);
    }

    public function claim(Request $request)
    {
        $data = $request->validate(['order_id' => ['required', 'integer']]);
        abort_unless(in_array((int) $data['order_id'], $request->session()->get('commerce.orders', []), true), 404);
        DB::transaction(function () use ($request, $data) {
            $order = Order::whereKey($data['order_id'])->whereNotNull('placed_at')->lockForUpdate()->firstOrFail();
            abort_unless($order->user_id === null || (int) $order->user_id === $request->user()->id, 404);
            if ($order->user_id === null) $order->update(['user_id' => $request->user()->id]);
        });
        return redirect('/account');
    }
}
