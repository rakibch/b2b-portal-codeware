<?php

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        if ($user->isBuyer()) {
            return $user->company_id === $order->company_id && $user->can('orders.view_company');
        }

        return $user->can('orders.view') && $order->isVisibleTo($user);
    }

    /** Buyers may cancel only while pending: the placer, or a Buyer Admin. Staff per permission. */
    public function cancel(User $user, Order $order): bool
    {
        if ($user->isBuyer()) {
            return $user->company_id === $order->company_id
                && $order->status === OrderStatus::Pending
                && $user->can('orders.cancel_own')
                && ($order->placed_by === $user->id || $user->isBuyerAdmin());
        }

        return $user->can('orders.cancel') && $order->isVisibleTo($user);
    }

    public function approve(User $user, Order $order): bool
    {
        return $user->isBuyer()
            && $user->company_id === $order->company_id
            && $user->can('orders.approve')
            && $order->placed_by !== $user->id;
    }

    public function reorder(User $user, Order $order): bool
    {
        return $user->isBuyer() && $user->company_id === $order->company_id && $user->can('orders.place');
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $user->isStaff() && $user->can('orders.update_status') && $order->isVisibleTo($user);
    }

    public function fulfil(User $user, Order $order): bool
    {
        return $user->isStaff() && $user->can('orders.fulfil') && $order->isVisibleTo($user);
    }

    public function pay(User $user, Order $order): bool
    {
        return $user->isBuyer() && $user->company_id === $order->company_id && $user->can('payments.submit');
    }
}
