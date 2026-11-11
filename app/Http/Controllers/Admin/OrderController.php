<?php

namespace App\Http\Controllers\Admin;

use App\Commerce\OrderWorkflow;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lunar\Models\Order;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Order::class);
        $query = Order::whereNotNull('placed_at')->with(['billingAddress', 'currency']);
        if ($request->filled('search')) {
            $search = '%'.mb_substr($request->string('search')->toString(), 0, 200).'%';
            $query->where(fn ($q) => $q->where('reference', 'like', $search)->orWhereHas('billingAddress', fn ($a) => $a->where('last_name', 'like', $search)->orWhere('contact_email', 'like', $search)));
        }
        if (isset(OrderWorkflow::LABELS[$request->query('status')])) $query->where('status', $request->query('status'));
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $perPage = in_array((int) $request->query('per_page'), [10, 25, 50, 100]) ? (int) $request->query('per_page') : 10;
        return Inertia::render('Admin/Index', ['resource' => 'orders', 'title' => 'Заказы', 'canCreate' => false, 'canDelete' => false,
            'columns' => collect(['reference' => 'Номер', 'status' => 'Статус', 'total' => 'Итого', 'placed_at' => 'Создан', 'last_name' => 'Фамилия', 'email' => 'Email'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'visible' => true, 'sortable' => $key === 'placed_at', 'type' => $key === 'placed_at' ? 'datetime-local' : 'text'])->values(),
            'records' => $query->orderBy('placed_at', $direction)->orderBy('id')->paginate($perPage)->withQueryString()->through(fn ($o) => [
                'id' => $o->id, 'reference' => $o->reference, 'status' => OrderWorkflow::LABELS[$o->status] ?? $o->status,
                'total' => $o->total->formatted, 'placed_at' => $o->placed_at, 'last_name' => $o->billingAddress?->last_name, 'email' => $o->billingAddress?->contact_email]),
            'filters' => [['key' => 'status', 'label' => 'Статус', 'options' => OrderWorkflow::LABELS]],
            'query' => [...$request->only(['search', 'status']), 'sort' => 'placed_at', 'direction' => $direction, 'per_page' => $perPage]]);
    }

    public function show(string $record)
    {
        $order = Order::whereNotNull('placed_at')->with(['billingAddress', 'lines', 'currency'])->findOrFail($record);
        Gate::authorize('view', $order);
        return Inertia::render('Admin/Order', ['order' => [
            'id' => $order->id, 'reference' => $order->reference, 'status' => $order->status, 'statusLabel' => OrderWorkflow::LABELS[$order->status] ?? $order->status,
            'placed_at' => $order->placed_at, 'total' => $order->total->formatted, 'prototype' => (bool) ($order->meta['prototype'] ?? false), 'notes' => $order->meta['notes'] ?? null, 'delivery' => $order->meta['delivery'] ?? null, 'company' => $order->meta['company'] ?? null,
            'contact' => $order->billingAddress?->only(['first_name', 'last_name', 'contact_email', 'contact_phone', 'company_name', 'city', 'line_one', 'postcode']),
            'lines' => $order->lines->map(fn ($line) => ['id' => $line->id, 'description' => $line->description, 'sku' => $line->meta['sku'] ?? null, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price->formatted, 'total' => $line->total->formatted]),
            'history' => $order->activities()->where('log_name', 'order-status')->with('causer')->latest('id')->get()->map(fn ($log) => [
                'id' => $log->id, 'date' => $log->created_at, 'actor' => $log->causer?->name ?? 'Удалённый сотрудник',
                'transition' => (OrderWorkflow::LABELS[$log->properties['from']] ?? $log->properties['from']).' → '.(OrderWorkflow::LABELS[$log->properties['to']] ?? $log->properties['to']), 'reason' => $log->properties['reason']]),
        ], 'transitions' => Gate::allows('update', $order) ? OrderWorkflow::options($order->status) : (object) []]);
    }

    public function status(Request $request, string $record)
    {
        $order = Order::whereNotNull('placed_at')->findOrFail($record);
        Gate::authorize('update', $order);
        $data = $request->validate(['expected' => 'required|string|max:100', 'status' => 'required|string|max:100', 'reason' => 'required|string|max:1000']);
        app(OrderWorkflow::class)->change($order, $request->user(), $data['expected'], $data['status'], $data['reason']);
        return back()->with('success', 'Статус обновлён.');
    }
}
