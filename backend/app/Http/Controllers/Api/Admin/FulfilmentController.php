<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateShipmentRequest;
use App\Http\Requests\Admin\DeliverShipmentRequest;
use App\Http\Requests\Admin\DispatchShipmentRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Order\FulfilmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Warehouse / dispatch desk. */
class FulfilmentController extends Controller
{
    public function __construct(private readonly FulfilmentService $fulfilment) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ShipmentStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $shipments = Shipment::query()
            ->whereHas('order', fn ($q) => $q->visibleTo($request->user()))
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->with(['order:id,order_number', 'items.orderItem'])
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 25));

        return ShipmentResource::collection($shipments);
    }

    public function store(CreateShipmentRequest $request, Order $order): JsonResponse
    {
        Gate::authorize('fulfil', $order);

        $shipment = $this->fulfilment->createShipment($order, $request->user(), $request->quantities(), $request->safe()->except('items'));

        return (new ShipmentResource($shipment->load(['order:id,order_number', 'items.orderItem'])))
            ->additional(['message' => __('messages.shipments.created', ['number' => $shipment->challan_number])])
            ->response()
            ->setStatusCode(201);
    }

    public function dispatch(DispatchShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('manage', $shipment);

        $shipment = $this->fulfilment->dispatch($shipment, $request->user(), $request->validated());

        return (new ShipmentResource($shipment->load(['order:id,order_number', 'items.orderItem'])))
            ->additional(['message' => __('messages.shipments.dispatched', ['number' => $shipment->challan_number])]);
    }

    public function deliver(DeliverShipmentRequest $request, Shipment $shipment): ShipmentResource
    {
        Gate::authorize('manage', $shipment);

        $shipment = $this->fulfilment->markDelivered($shipment, $request->user(), $request->validated('received_by'));

        return (new ShipmentResource($shipment->load(['order:id,order_number', 'items.orderItem'])))
            ->additional(['message' => __('messages.shipments.delivered', ['number' => $shipment->challan_number])]);
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        Gate::authorize('manage', $shipment);

        $this->fulfilment->cancelPacking($shipment);

        return response()->json(['message' => __('messages.shipments.cancelled', ['number' => $shipment->challan_number])]);
    }
}
