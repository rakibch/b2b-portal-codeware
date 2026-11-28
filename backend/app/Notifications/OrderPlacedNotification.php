<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Localized;

class OrderPlacedNotification extends BaseNotification
{
    public function __construct(public readonly Order $order)
    {
        parent::__construct();
    }

    protected function key(): string
    {
        return 'order_placed';
    }

    protected function replacements(): array
    {
        return ['number' => $this->order->order_number, 'company' => $this->order->company->name, 'total' => Localized::currency($this->order->grand_total)];
    }

    protected function path(): string
    {
        return '/admin/orders/'.$this->order->order_number;
    }
}
