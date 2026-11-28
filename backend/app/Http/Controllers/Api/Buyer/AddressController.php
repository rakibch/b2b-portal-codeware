<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\CompanyAddress;
use App\Services\Company\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class AddressController extends Controller
{
    public function __construct(private readonly AddressService $addresses) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CompanyAddress::class);

        $addresses = $request->user()->company->addresses()
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->orderByDesc('is_default')
            ->orderBy('label')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(AddressRequest $request): JsonResponse
    {
        Gate::authorize('create', CompanyAddress::class);

        $address = $this->addresses->create($request->user()->company, $request->validated());

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function show(CompanyAddress $address): AddressResource
    {
        Gate::authorize('view', $address);

        return new AddressResource($address);
    }

    public function update(AddressRequest $request, CompanyAddress $address): AddressResource
    {
        Gate::authorize('update', $address);

        return new AddressResource($this->addresses->update($address, $request->validated()));
    }

    public function destroy(CompanyAddress $address): JsonResponse
    {
        Gate::authorize('delete', $address);

        $this->addresses->delete($address);

        return response()->json(['message' => __('messages.common.deleted')]);
    }
}
