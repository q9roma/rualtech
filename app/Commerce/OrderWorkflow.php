<?php

namespace App\Commerce;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lunar\Models\Order;

class OrderWorkflow
{
    public const LABELS = [
        'prototype-pending-confirmation' => 'Ожидает подтверждения',
        'prototype-confirmed' => 'Подтверждён',
        'prototype-ready' => 'Готов к выдаче',
        'prototype-completed' => 'Завершён',
        'prototype-cancelled' => 'Отменён',
    ];

    public static function options(string $status): array
    {
        $next = match ($status) {
            'prototype-pending-confirmation' => ['prototype-confirmed', 'prototype-cancelled'],
            'prototype-confirmed' => ['prototype-ready', 'prototype-cancelled'],
            'prototype-ready' => ['prototype-completed', 'prototype-cancelled'],
            default => [],
        };
        return array_intersect_key(self::LABELS, array_flip($next));
    }

    public function change(Order $order, User $actor, string $expected, string $status, string $reason): void
    {
        Gate::forUser($actor)->authorize('update', $order);
        DB::transaction(function () use ($order, $actor, $expected, $status, $reason) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $locked);
            if ($locked->status !== $expected || ! isset(self::options($locked->status)[$status])) {
                throw ValidationException::withMessages(['status' => 'Статус изменился или переход недопустим. Обновите страницу заказа.']);
            }
            $reason = trim($reason);
            if ($reason === '' || mb_strlen($reason) > 1000) {
                throw ValidationException::withMessages(['reason' => 'Укажите комментарий длиной до 1000 символов.']);
            }
            $locked->update(['status' => $status]);
            activity('order-status')->performedOn($locked)->causedBy($actor)
                ->withProperties(['from' => $expected, 'to' => $status, 'reason' => $reason])
                ->log('Изменён статус заказа');
        });
    }
}
