<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CustomerGroupRequest;
use App\Http\Resources\CustomerGroupResource;
use App\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerGroupController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CustomerGroupResource::collection(CustomerGroup::query()->withCount('companies')->orderBy('priority')->get());
    }

    public function store(CustomerGroupRequest $request): JsonResponse
    {
        return (new CustomerGroupResource(CustomerGroup::query()->create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(CustomerGroup $customerGroup): CustomerGroupResource
    {
        return new CustomerGroupResource($customerGroup->loadCount('companies'));
    }

    public function update(CustomerGroupRequest $request, CustomerGroup $customerGroup): CustomerGroupResource
    {
        $customerGroup->update($request->validated());

        return new CustomerGroupResource($customerGroup);
    }

    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        if ($customerGroup->companies()->exists()) {
            throw BusinessRuleException::make('customer_group_in_use');
        }

        $customerGroup->delete(); // cascades its group-specific tiers

        return response()->json(['message' => __('messages.common.deleted')]);
    }
}
