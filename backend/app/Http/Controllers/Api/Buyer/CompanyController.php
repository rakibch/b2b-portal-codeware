<?php

namespace App\Http\Controllers\Api\Buyer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Buyer\SubmitKycDocumentsRequest;
use App\Http\Requests\Buyer\UpdateCompanyProfileRequest;
use App\Http\Resources\CompanyResource;
use App\Models\AuditLog;
use App\Services\Kyc\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    public function show(Request $request): CompanyResource
    {
        $company = $request->user()->company;
        Gate::authorize('view', $company);

        return new CompanyResource($company->load(['customerGroup', 'accountManager', 'addresses', 'documents'])->loadCount('users'));
    }

    public function update(UpdateCompanyProfileRequest $request): CompanyResource
    {
        $company = $request->user()->company;
        Gate::authorize('update', $company);

        $old = $company->only(array_keys($request->validated()));
        $company->update($request->validated());
        AuditLog::record('company.profile_updated', $company, $old, $request->validated());

        return new CompanyResource($company->load(['customerGroup', 'accountManager']));
    }

    /** (Re)submit KYC documents — allowed while pending or after rejection. */
    public function submitDocuments(SubmitKycDocumentsRequest $request, KycService $kyc): JsonResponse
    {
        $company = $request->user()->company;
        Gate::authorize('update', $company);

        $company = $kyc->submitDocuments($company, $request->user(), $request->documents());

        return response()->json([
            'message' => __('messages.kyc.resubmitted'),
            'company' => new CompanyResource($company->load('documents')),
        ]);
    }
}
