<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UnitResource::collection(Unit::query()->orderBy('code')->get());
    }

    public function store(UnitRequest $request): JsonResponse
    {
        return (new UnitResource(Unit::query()->create($request->validated())))->response()->setStatusCode(201);
    }

    public function show(Unit $unit): UnitResource
    {
        return new UnitResource($unit);
    }

    public function update(UnitRequest $request, Unit $unit): UnitResource
    {
        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit): JsonResponse
    {
        if ($unit->products()->withTrashed()->exists()) {
            throw BusinessRuleException::make('unit_in_use');
        }

        $unit->delete();

        return response()->json(['message' => __('messages.common.deleted')]);
    }
}
