<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CompanyPriceRequest;
use App\Http\Resources\CompanyPriceResource;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyProductPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/** Customer-specific (contract) SKU price overrides. */
class CompanyPriceController extends Controller
{
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        Gate::authorize('managePricing', $company);

        return CompanyPriceResource::collection(
            $company->productPrices()->with(['product:id,sku,name,base_price', 'creator:id,name'])->latest('id')->paginate(50),
        );
    }

    public function store(CompanyPriceRequest $request, Company $company): JsonResponse
    {
        Gate::authorize('managePricing', $company);

        $price = $company->productPrices()->create($request->validated() + ['created_by' => $request->user()->id]);
        AuditLog::record('pricing.contract_created', $company, [], $price->only(['product_id', 'unit_price', 'min_quantity']));

        return (new CompanyPriceResource($price->load(['product:id,sku,name,base_price', 'creator:id,name'])))->response()->setStatusCode(201);
    }

    public function update(CompanyPriceRequest $request, Company $company, CompanyProductPrice $price): CompanyPriceResource
    {
        Gate::authorize('managePricing', $company);
        abort_unless($price->company_id === $company->id, 404);

        $old = $price->only(['unit_price', 'min_quantity', 'valid_from', 'valid_until']);
        $price->update($request->validated());
        AuditLog::record('pricing.contract_updated', $company, $old, $request->validated());

        return new CompanyPriceResource($price->load(['product:id,sku,name,base_price', 'creator:id,name']));
    }

    public function destroy(Company $company, CompanyProductPrice $price): JsonResponse
    {
        Gate::authorize('managePricing', $company);
        abort_unless($price->company_id === $company->id, 404);

        $price->delete();
        AuditLog::record('pricing.contract_deleted', $company, ['product_id' => $price->product_id], []);

        return response()->json(['message' => __('messages.common.deleted')]);
    }
}
