<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\CartItemSource;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\QuickOrderImportRequest;
use App\Http\Requests\Buyer\QuickOrderRowsRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\QuickOrderResultResource;
use App\Services\Cart\CartService;
use App\Services\Cart\QuickOrderService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuickOrderController extends Controller
{
    public function __construct(
        private readonly QuickOrderService $quickOrder,
        private readonly CartService $cart,
    ) {}

    /** Live validation of pad rows: matching, MOQ/step/stock and pricing per row. */
    public function validateRows(QuickOrderRowsRequest $request): QuickOrderResultResource
    {
        return new QuickOrderResultResource($this->quickOrder->resolve($request->validated('rows'), $request->user()->company));
    }

    /** Adds all rows in one click. Fails atomically if any matched row is invalid or a SKU is unknown. */
    public function addToCart(QuickOrderRowsRequest $request): CartResource
    {
        $result = $this->quickOrder->resolve($request->validated('rows'), $request->user()->company);

        if ($result['unmatched'] !== []) {
            throw BusinessRuleException::make('quick_order_unmatched', [], 422, ['unmatched' => $result['unmatched']]);
        }

        $quantities = [];
        foreach ($result['matched'] as $line) {
            $quantities[$line['product']->id] = $line['quote']->quantity;
        }

        $this->cart->addMany($request->user(), $quantities, CartItemSource::QuickOrder);

        return new CartResource($this->cart->summary($request->user()));
    }

    /** CSV/XLSX upload → preview of matched / unmatched rows (nothing is added yet). */
    public function import(QuickOrderImportRequest $request): QuickOrderResultResource
    {
        return new QuickOrderResultResource($this->quickOrder->import($request->file('file'), $request->user()->company));
    }

    public function template(): StreamedResponse
    {
        return $this->quickOrder->template();
    }
}
