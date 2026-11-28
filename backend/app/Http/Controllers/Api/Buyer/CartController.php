<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\CartItemSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\CartItemsRequest;
use App\Http\Requests\Buyer\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(Request $request): CartResource
    {
        return new CartResource($this->cart->summary($request->user()));
    }

    public function addItems(CartItemsRequest $request): CartResource
    {
        $this->cart->addMany(
            $request->user(),
            $request->quantities(),
            CartItemSource::tryFrom((string) $request->validated('source')) ?? CartItemSource::Catalog,
            ($request->validated('mode') ?? 'merge') === 'merge',
        );

        return new CartResource($this->cart->summary($request->user()));
    }

    public function updateItem(UpdateCartItemRequest $request, CartItem $item): CartResource
    {
        Gate::authorize('update', $item);

        $this->cart->updateQuantity($item, (int) $request->validated('quantity'));

        return new CartResource($this->cart->summary($request->user()));
    }

    public function removeItem(Request $request, CartItem $item): CartResource
    {
        Gate::authorize('delete', $item);

        $this->cart->remove($item);

        return new CartResource($this->cart->summary($request->user()));
    }

    public function clear(Request $request): CartResource
    {
        $this->cart->clear($request->user());

        return new CartResource($this->cart->summary($request->user()));
    }
}
