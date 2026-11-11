<?php

namespace App\Policies;

use App\Models\User;
use Lunar\Models\Order;

class OrderPolicy
{
    public function viewAny(User $user): bool { return $user->is_admin; }
    public function view(User $user, Order $order): bool { return $user->is_admin && $order->placed_at !== null; }
    public function update(User $user, Order $order): bool { return $this->view($user, $order) && ($order->meta['prototype'] ?? false) === true; }
    public function create(User $user): bool { return false; }
    public function delete(User $user, Order $order): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
