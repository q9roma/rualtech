<?php

namespace App\Http\Middleware;

use App\Admin\Resources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Middleware;

class AdminInertiaRequests extends Middleware
{
    protected $rootView = 'admin';

    public function share(Request $request): array
    {
        return [...parent::share($request),
            'admin' => fn () => $request->user()?->is_admin ? [
                'name' => $request->user()->name, 'email' => $request->user()->email,
                'navigation' => collect(Resources::LABELS)->filter(fn ($label, $key) => Gate::allows('viewAny', Resources::MODELS[$key] ?? \Lunar\Models\Order::class))
                    ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'url' => url('/admin/'.$key)])->values()
                    ->when(Gate::allows('create', \App\Models\Product::class), fn ($items) => $items->push(['key' => 'catalog-prices', 'label' => 'Обновить прайс', 'url' => url('/admin/catalog-prices')])),
                'unread' => $request->user()->unreadNotifications()->count(),
            ] : null,
            'flash' => ['success' => fn () => $request->session()->get('success')],
        ];
    }
}
