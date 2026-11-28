<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\OrderApprovalStatus;
use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Requests\Buyer\CheckoutRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Rfq;
use App\Services\Cart\CartService;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const DETAIL = ['items', 'placedBy:id,name', 'createdByStaff:id,name', 'approver:id,name', 'rfq:id,reference', 'statusHistory.changer:id,name', 'shipments.items.orderItem', 'invoice'];

    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'approval' => ['nullable', Rule::enum(OrderApprovalStatus::class)],
            'q' => ['nullable', 'string', 'max:64'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        abort_unless($request->user()->can('orders.view_company'), 403);

        $orders = Order::query()
            ->visibleTo($request->user())
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($validated['approval'] ?? null, fn ($q, $s) => $q->where('approval_status', $s))
            ->when($validated['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('order_number', 'like', '%'.addcslashes($t, '%_\\').'%')->orWhere('po_number', 'like', '%'.addcslashes($t, '%_\\').'%')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('placed_by', $request->user()->id))
            ->with('placedBy:id,name')
            ->withCount('items')
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return OrderResource::collection($orders);
    }

    public function store(CheckoutRequest $request): JsonResponse
    {
        $order = $this->orders->checkout($request->user(), $request->toData());

        return $this->created($order);
    }

    /** Converts an accepted / acceptable RFQ quotation into an order. */
    public function fromRfq(CheckoutRequest $request, Rfq $rfq): JsonResponse
    {
        Gate::authorize('respond', $rfq);

        return $this->created($this->orders->convertRfq($request->user(), $rfq, $request->toData()));
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(self::DETAIL));
    }

    public function cancel(ReasonRequest $request, Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        $order = $this->orders->cancel($order, $request->user(), $request->validated('reason'));

        return (new OrderResource($order->load(self::DETAIL)))->additional(['message' => __('messages.orders.cancelled', ['number' => $order->order_number])]);
    }

    public function approve(Request $request, Order $order): OrderResource
    {
        Gate::authorize('approve', $order);

        $order = $this->orders->approve($order, $request->user());

        return (new OrderResource($order->load(self::DETAIL)))->additional(['message' => __('messages.orders.approved', ['number' => $order->order_number])]);
    }

    public function rejectApproval(ReasonRequest $request, Order $order): OrderResource
    {
        Gate::authorize('approve', $order);

        $order = $this->orders->rejectApproval($order, $request->user(), $request->validated('reason'));

        return (new OrderResource($order->load(self::DETAIL)))->additional(['message' => __('messages.orders.rejected', ['number' => $order->order_number])]);
    }

    public function reorder(Request $request, Order $order, CartService $cart): JsonResponse
    {
        Gate::authorize('reorder', $order);

        $result = $this->orders->reorder($order, $request->user());

        return response()->json([
            'message' => __('messages.orders.reorder_done', ['added' => count($result['added']), 'skipped' => count($result['skipped'])]),
            'added' => $result['added'],
            'skipped' => $result['skipped'],
            'cart' => new CartResource($cart->summary($request->user())),
        ]);
    }

    private function created(Order $order): JsonResponse
    {
        $key = $order->approval_status === OrderApprovalStatus::Pending ? 'messages.orders.placed_needs_approval' : 'messages.orders.placed';

        return (new OrderResource($order->load(self::DETAIL)))
            ->additional(['message' => __($key, ['number' => $order->order_number])])
            ->response()
            ->setStatusCode(201);
    }
}
