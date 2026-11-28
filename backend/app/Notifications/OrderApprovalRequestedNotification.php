<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Localized;

class OrderApprovalRequestedNotification extends BaseNotification
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'order_approval_requested';
    }

    protected function replacements(): array
    {
        return ['number' => $this->order->order_number, 'name' => $this->order->placedBy->name, 'total' => Localized::currency($this->order->grand_total)];
    }

    protected function path(): string
    {
        return '/orders/'.$this->order->order_number;
    }
}
