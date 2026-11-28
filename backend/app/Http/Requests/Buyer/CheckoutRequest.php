<?php

namespace App\Http\Requests\Buyer;

use App\Enums\PaymentMethod;
use App\Services\Order\CheckoutData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for cart checkout and RFQ → order conversion. */
class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.place');
    }

    public function rules(): array
    {
        return [
            'shipping_address_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'po_number' => ['nullable', 'string', 'max:64'],
            'buyer_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): CheckoutData
    {
        return CheckoutData::fromArray($this->validated());
    }
}
