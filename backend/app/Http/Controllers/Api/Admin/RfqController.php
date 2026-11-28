<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\RfqStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuoteRfqRequest;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Resources\RfqResource;
use App\Models\Rfq;
use App\Services\Rfq\RfqService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RfqController extends Controller
{
    private const RELATIONS = ['items.product.unit', 'requester:id,name', 'assignee:id,name', 'company:id,public_id,name'];

    public function __construct(private readonly RfqService $rfqs) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(RfqStatus::class)],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $rfqs = Rfq::query()
            ->visibleTo($request->user())
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('mine'), fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->with(['requester:id,name', 'assignee:id,name', 'company:id,public_id,name'])
            ->withCount('items')
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return RfqResource::collection($rfqs);
    }

    public function show(Rfq $rfq): RfqResource
    {
        Gate::authorize('view', $rfq);

        return new RfqResource($rfq->load(self::RELATIONS));
    }

    public function startReview(Request $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('quote', $rfq);

        return new RfqResource($this->rfqs->startReview($rfq, $request->user())->load(self::RELATIONS));
    }

    public function quote(QuoteRfqRequest $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('quote', $rfq);

        $rfq = $this->rfqs->quote(
            $rfq,
            $request->user(),
            $request->prices(),
            $request->validated('valid_until'),
            $request->validated('quote_notes'),
            $request->validated('internal_notes'),
        );

        return (new RfqResource($rfq->load(self::RELATIONS)))->additional(['message' => __('messages.rfq.quoted', ['reference' => $rfq->reference])]);
    }

    public function decline(ReasonRequest $request, Rfq $rfq): RfqResource
    {
        Gate::authorize('quote', $rfq);

        return new RfqResource($this->rfqs->decline($rfq, $request->user(), $request->validated('reason'))->load(self::RELATIONS));
    }
}
