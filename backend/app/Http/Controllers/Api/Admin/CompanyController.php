<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCreditTermsRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\Company;
use App\Services\Kyc\KycService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(KycStatus::class)],
            'customer_group_id' => ['nullable', 'integer'],
            'account_manager_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['name', 'newest', 'outstanding'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $companies = Company::query()
            ->visibleTo($request->user())
            ->when($validated['status'] ?? null, fn ($q, $s) => $q->where('kyc_status', $s))
            ->when($validated['customer_group_id'] ?? null, fn ($q, $id) => $q->where('customer_group_id', $id))
            ->when($validated['account_manager_id'] ?? null, fn ($q, $id) => $q->where('account_manager_id', $id))
            ->when($validated['q'] ?? null, function ($q, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('trade_license_no', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->with(['customerGroup', 'accountManager:id,name,email,phone'])
            ->withCount('users');

        match ($validated['sort'] ?? 'name') {
            'newest' => $companies->latest('id'),
            'outstanding' => $companies->orderByDesc('outstanding_balance'),
            default => $companies->orderBy('name'),
        };

        return CompanyResource::collection($companies->paginate((int) ($validated['per_page'] ?? 20)));
    }

    public function show(Company $company): CompanyResource
    {
        Gate::authorize('view', $company);

        return new CompanyResource($company->load(['customerGroup', 'accountManager:id,name,email,phone', 'addresses', 'documents', 'kycReviewer:id,name'])->loadCount('users'));
    }

    public function users(Company $company): AnonymousResourceCollection
    {
        Gate::authorize('view', $company);

        return UserResource::collection($company->users()->with('roles:id,name')->orderBy('name')->get());
    }

    public function updateTerms(UpdateCreditTermsRequest $request, Company $company, KycService $kyc): CompanyResource
    {
        Gate::authorize('manageCredit', $company);

        $company = $kyc->updateTerms($company, $request->user(), $request->validated());

        return (new CompanyResource($company->load(['customerGroup', 'accountManager:id,name,email,phone'])))
            ->additional(['message' => __('messages.company.terms_updated')]);
    }
}
