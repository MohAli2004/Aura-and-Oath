<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isCustomer();
    }

    public function view(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $order->isOwnedBy($user);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function approve(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function cancel(User $user, Order $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $order->isOwnedBy($user) && $order->status === \App\Enums\OrderStatus::PendingApproval;
    }
}
