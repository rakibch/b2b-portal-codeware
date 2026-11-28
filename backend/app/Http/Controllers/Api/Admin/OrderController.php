<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderApprovalStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Requests\Admin\StaffOrderRequest;
use App\Http\Requests\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    private const DETAIL = ['company:id,public_id,name', 'items', 'placedBy:id,name', 'createdByStaff:id,name', 'approver:id,name', 'rfq:id,reference', 'statusHistory.changer:id,name', 'shipments.items.orderItem', 'invoice'];

    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(OrderStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'company_id' => ['nullable', 'string', 'size:26'],
            'q' => ['nullable', 'string', 'max:64'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $orders = Order::query()
            ->visibleTo($request->user())
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->whereIn('status', $s))
            ->when($validated['payment_method'] ?? null, fn ($q, $m) => $q->where('payment_method', $m))
            ->when($validated['company_id'] ?? null, fn ($q, $id) => $q->whereHas('company', fn ($c) => $c->where('public_id', $id)))
            ->when($validated['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('order_number', 'like', '%'.addcslashes($t, '%_\\').'%')->orWhere('po_number', 'like', '%'.addcslashes($t, '%_\\').'%')))
            ->when($validated['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($validated['to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->where('approval_status', '!=', OrderApprovalStatus::Pending->value)
            ->with(['company:id,public_id,name', 'placedBy:id,name'])
            ->withCount('items')
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return OrderResource::collection($orders);
    }

    public function show(Order $order): OrderResource
    {
        Gate::authorize('view', $order);

        return new OrderResource($order->load(self::DETAIL));
    }

    /** Sales / Account Manager creates an order on behalf of a buyer user. */
    public function store(StaffOrderRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('placeOrderOnBehalf', $company);

        $buyer = User::query()->buyers()->where('company_id', $company->id)->findOrFail($request->validated('buyer_user_id'));
        $order = $this->orders->placeOnBehalf($request->user(), $company, $buyer, $request->lines(), $request->toData());

        return (new OrderResource($order->load(self::DETAIL)))
            ->additional(['message' => __('messages.orders.placed', ['number' => $order->order_number])])
            ->response()
            ->setStatusCode(201);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        Gate::authorize('updateStatus', $order);

        $order = $this->orders->transition($order, OrderStatus::from($request->validated('status')), $request->user(), $request->validated('comment'));
        $key = $order->status === OrderStatus::Confirmed ? 'messages.orders.confirmed' : 'messages.orders.status_updated';

        return (new OrderResource($order->load(self::DETAIL)))
            ->additional(['message' => __($key, ['number' => $order->order_number, 'status' => $order->status->label()])]);
    }

    public function cancel(ReasonRequest $request, Order $order): OrderResource
    {
        Gate::authorize('cancel', $order);

        $order = $this->orders->cancel($order, $request->user(), $request->validated('reason'));

        return (new OrderResource($order->load(self::DETAIL)))->additional(['message' => __('messages.orders.cancelled', ['number' => $order->order_number])]);
    }
}
