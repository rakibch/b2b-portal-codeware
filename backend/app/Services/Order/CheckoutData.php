<?php

namespace App\Services\Order;

use App\Enums\PaymentMethod;

/** Validated checkout input (built by CheckoutRequest / staff order request). */
final readonly class CheckoutData
{
    public function __construct(
        public int $shippingAddressId,
        public PaymentMethod $paymentMethod,
        public ?string $poNumber = null,
        public ?string $buyerNotes = null,
        public ?string $internalNotes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            shippingAddressId: (int) $data['shipping_address_id'],
            paymentMethod: PaymentMethod::from($data['payment_method']),
            poNumber: $data['po_number'] ?? null,
            buyerNotes: $data['buyer_notes'] ?? null,
            internalNotes: $data['internal_notes'] ?? null,
        );
    }
}
