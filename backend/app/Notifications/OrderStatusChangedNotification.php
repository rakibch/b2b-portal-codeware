<?php

namespace App\Notifications;

use App\Models\Order;

class OrderStatusChangedNotification extends BaseNotification
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'order_status_changed';
    }

    protected function replacements(): array
    {
        return ['number' => $this->order->order_number, 'status' => $this->order->status->label()];
    }

    protected function path(): string
    {
        return '/orders/'.$this->order->order_number;
    }
}
