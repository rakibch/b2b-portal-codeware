<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Services\Order\CheckoutData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sales / Account Manager creates an order on behalf of a buyer. */
class StaffOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.create_on_behalf');
    }

    public function rules(): array
    {
        return [
            'buyer_user_id' => ['required', 'integer', 'min:1'],
            'shipping_address_id' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', Rule::in([PaymentMethod::Credit->value, PaymentMethod::Cod->value, PaymentMethod::BankTransfer->value])],
            'po_number' => ['nullable', 'string', 'max:64'],
            'buyer_notes' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'min:1'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000000'],
        ];
    }

    /** @return array<int, int> */
    public function lines(): array
    {
        return collect($this->validated('items'))->mapWithKeys(fn ($i) => [(int) $i['product_id'] => (int) $i['quantity']])->all();
    }

    public function toData(): CheckoutData
    {
        return CheckoutData::fromArray($this->validated());
    }
}
