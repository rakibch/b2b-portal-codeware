<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

class ShipmentPolicy
{
    public function view(User $user, Shipment $shipment): bool
    {
        return app(OrderPolicy::class)->view($user, $shipment->order);
    }

    public function manage(User $user, Shipment $shipment): bool
    {
        return app(OrderPolicy::class)->fulfil($user, $shipment->order);
    }
}
