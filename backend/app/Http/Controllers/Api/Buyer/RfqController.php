<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\StoreRfqRequest;
use App\Http\Resources\RfqResource;
use App\Models\Rfq;
use App\Services\Rfq\RfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RfqController extends Controller
{
    public function __construct(private readonly RfqService $rfqs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::enum(RfqStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rfqs = Rfq::query()
            ->visibleTo($request->user())
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with('requester:id,name')
            ->withCount('items')
            ->latest('id')
            ->paginate((int) $request->query('per_page', 20));

        return RfqResource::collection($rfqs);
    }

    public function store(StoreRfqRequest $request): JsonResponse
    {
        $rfq = $this->rfqs->create($request->user(), $request->validated());

        return (new RfqResource($rfq->load(['items.product.unit', 'requester:id,name'])))
            ->additional(['message' => __('messages.rfq.submitted', ['reference' => $rfq->reference])])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Rfq $rfq): RfqResource
    {
        Gate::authorize('view', $rfq);

        return new RfqResource($rfq->load(['items.product.unit', 'requester:id,name']));
    }

    public function accept(Request $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('respond', $rfq);

        return new RfqResource($this->rfqs->accept($rfq, $request->user())->load(['items.product.unit', 'requester:id,name']));
    }

    public function reject(Request $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('respond', $rfq);

        return new RfqResource($this->rfqs->reject($rfq, $request->user())->load(['items.product.unit', 'requester:id,name']));
    }

    public function cancel(Request $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('cancel', $rfq);

        return new RfqResource($this->rfqs->cancel($rfq, $request->user())->load(['items.product.unit', 'requester:id,name']));
    }
}
