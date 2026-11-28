<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DocumentStatus;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveKycRequest;
use App\Http\Requests\Admin\ReasonRequest;
use App\Http\Requests\Admin\ReviewDocumentRequest;
use App\Http\Resources\CompanyDocumentResource;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Services\Kyc\KycService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KycController extends Controller
{
    public function __construct(private readonly KycService $kyc) {}

    /** KYC queue (defaults to pending, oldest first = FIFO review). */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(KycStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $validated['status'] ?? KycStatus::Pending->value;

        $companies = Company::query()
            ->visibleTo($request->user())
            ->where('kyc_status', $status)
            ->when($validated['q'] ?? null, function ($q, string $term): void {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('trade_license_no', 'like', $like)->orWhere('phone', 'like', $like));
            })
            ->with(['customerGroup', 'accountManager:id,name,email,phone'])
            ->withCount('users')
            ->orderBy($status === KycStatus::Pending->value ? 'kyc_submitted_at' : 'kyc_reviewed_at', $status === KycStatus::Pending->value ? 'asc' : 'desc')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return CompanyResource::collection($companies);
    }

    public function show(Company $company): CompanyResource
    {
        Gate::authorize('reviewKyc', $company);

        return new CompanyResource($company->load(['customerGroup', 'accountManager:id,name,email,phone', 'addresses', 'documents', 'kycReviewer:id,name'])->loadCount('users'));
    }

    public function approve(ApproveKycRequest $request, Company $company): CompanyResource
    {
        Gate::authorize('reviewKyc', $company);

        $company = $this->kyc->approve($company, $request->user(), $request->validated());

        return (new CompanyResource($company->load(['customerGroup', 'accountManager:id,name,email,phone', 'documents'])))
            ->additional(['message' => __('messages.kyc.approved', ['company' => $company->name])]);
    }

    public function reject(ReasonRequest $request, Company $company): CompanyResource
    {
        Gate::authorize('reviewKyc', $company);

        $company = $this->kyc->reject($company, $request->user(), $request->validated('reason'));

        return (new CompanyResource($company))->additional(['message' => __('messages.kyc.rejected', ['company' => $company->name])]);
    }

    public function suspend(ReasonRequest $request, Company $company): CompanyResource
    {
        Gate::authorize('reviewKyc', $company);

        $company = $this->kyc->suspend($company, $request->user(), $request->validated('reason'));

        return (new CompanyResource($company))->additional(['message' => __('messages.kyc.suspended', ['company' => $company->name])]);
    }

    public function reviewDocument(ReviewDocumentRequest $request, Company $company, CompanyDocument $document): CompanyDocumentResource
    {
        abort_unless($document->company_id === $company->id, 404);
        Gate::authorize('review', $document);

        return new CompanyDocumentResource($this->kyc->reviewDocument(
            $document,
            $request->user(),
            DocumentStatus::from($request->validated('status')),
            $request->validated('notes'),
        ));
    }
}
