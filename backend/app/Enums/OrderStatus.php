<?php

namespace App\Enums;

enum OrderStatus: string
{
    use EnumHelpers;

    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case PartiallyDispatched = 'partially_dispatched';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /** Allowed state-machine transitions. */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::PartiallyDispatched, self::Dispatched, self::Cancelled],
            self::PartiallyDispatched => [self::Dispatched],
            self::Dispatched => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function isCancellable(): bool
    {
        return $this->canTransitionTo(self::Cancelled);
    }

    /** Statuses that hold reserved (decremented) stock. */
    public static function stockHolding(): array
    {
        return [self::Pending, self::Confirmed, self::Processing, self::PartiallyDispatched];
    }
}
