<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Product, ContactRequest, Service, BlogPost};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lunar\Models\Order;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $days = in_array((int) $request->query('days'), [7, 30, 90]) ? (int) $request->query('days') : 30;
        $start = now()->startOfDay()->subDays($days - 1);
        $canRequests = Gate::allows('viewAny', ContactRequest::class);
        $canOrders = Gate::allows('viewAny', Order::class);
        $requests = $canRequests ? ContactRequest::where('created_at', '>=', $start)->selectRaw('DATE(created_at) as day, COUNT(*) as total')->groupByRaw('DATE(created_at)')->pluck('total', 'day') : collect();
        // "total" is a Lunar Price cast; aggregate into a distinct scalar alias.
        $orders = $canOrders ? Order::where('placed_at', '>=', $start)->selectRaw('DATE(placed_at) as day, COUNT(*) as aggregate_count')->groupByRaw('DATE(placed_at)')->pluck('aggregate_count', 'day') : collect();
        $series = collect(range(0, $days - 1))->map(function ($offset) use ($start, $requests, $orders) {
            $day = $start->copy()->addDays($offset)->format('Y-m-d');
            return ['date' => $day, 'requests' => (int) ($requests[$day] ?? 0), 'orders' => (int) ($orders[$day] ?? 0)];
        });
        return Inertia::render('Admin/Dashboard', ['days' => $days, 'series' => $series,
            'stats' => collect([
                ['label' => 'Товары в каталоге', 'model' => Product::class, 'url' => '/admin/products', 'hint' => 'Управление ассортиментом', 'query' => fn () => Product::count()],
                ['label' => 'Новые заявки', 'model' => ContactRequest::class, 'url' => '/admin/contact-requests?status=new', 'hint' => 'Ожидают обработки', 'query' => fn () => ContactRequest::where('status', 'new')->count()],
                ['label' => 'Заказы', 'model' => Order::class, 'url' => '/admin/orders', 'hint' => 'Оформленные заказы', 'query' => fn () => Order::whereNotNull('placed_at')->count()],
                ['label' => 'Активные услуги', 'model' => Service::class, 'url' => '/admin/services', 'hint' => 'Опубликованы на сайте', 'query' => fn () => Service::where('is_active', true)->count()],
            ])->filter(fn ($stat) => Gate::allows('viewAny', $stat['model']))->map(fn ($stat) => ['label' => $stat['label'], 'value' => $stat['query'](), 'url' => $stat['url'], 'hint' => $stat['hint']])->values(),
            'recent' => $canRequests ? ContactRequest::latest()->limit(8)->get(['id', 'name', 'email', 'service', 'status', 'created_at'])->map(fn ($r) => [...$r->toArray(), 'statusLabel' => $r->status_label]) : [],
        ]);
    }
}
