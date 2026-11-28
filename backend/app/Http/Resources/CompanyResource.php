<?php

namespace App\Http\Resources;

use App\Enums\KycStatus;
use App\Models\Company;
use Illuminate\Http\Request;

/** @mixin Company */
class CompanyResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $staff = $this->viewerIsStaff($request);
        $showCredit = $staff || $this->kyc_status === KycStatus::Approved;

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'business_type' => $this->enum($this->business_type),
            'contact_person' => $this->contact_person,
            'email' => $this->email,
            'phone' => $this->phone,
            'trade_license_no' => $this->trade_license_no,
            'tin_no' => $this->tin_no,
            'bin_no' => $this->bin_no,
            'website' => $this->website,
            'kyc' => [
                'status' => $this->enum($this->kyc_status),
                'submitted_at' => $this->kyc_submitted_at?->toIso8601String(),
                'reviewed_at' => $this->kyc_reviewed_at?->toIso8601String(),
                'rejection_reason' => $this->kyc_rejection_reason,
                'reviewed_by' => $this->when($staff && $this->relationLoaded('kycReviewer'), fn () => $this->kycReviewer?->only(['id', 'name'])),
            ],
            'credit' => $this->when($showCredit, fn () => [
                'credit_limit' => $this->credit_limit,
                'payment_terms_days' => $this->payment_terms_days,
                'outstanding_balance' => $this->outstanding_balance,
                'available_credit' => $this->availableCredit(),
                'credit_enabled' => $this->credit_enabled,
                'credit_on_hold' => $this->credit_on_hold,
            ]),
            'customer_group' => new CustomerGroupResource($this->whenLoaded('customerGroup')),
            'account_manager' => $this->whenLoaded('accountManager', fn () => $this->accountManager?->only(['id', 'name', 'email', 'phone'])),
            'addresses' => AddressResource::collection($this->whenLoaded('addresses')),
            'documents' => CompanyDocumentResource::collection($this->whenLoaded('documents')),
            'users_count' => $this->whenCounted('users'),
            'internal_notes' => $this->when($staff, $this->internal_notes),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
