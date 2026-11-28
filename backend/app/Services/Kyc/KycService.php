<?php

namespace App\Services\Kyc;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Enums\Role;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\User;
use App\Notifications\KycApprovedNotification;
use App\Notifications\KycRejectedNotification;
use App\Notifications\KycSubmittedNotification;
use App\Notifications\KycSuspendedNotification;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class KycService
{
    public function __construct(private readonly KycDocumentStorage $storage) {}

    /**
     * Approves KYC. Credit terms are applied only when supplied (caller authorises credit.manage).
     *
     * @param  array{credit_limit?: string, payment_terms_days?: int, customer_group_id?: ?int, account_manager_id?: ?int, credit_enabled?: bool}  $terms
     */
    public function approve(Company $company, User $reviewer, array $terms = []): Company
    {
        $company = DB::transaction(function () use ($company, $reviewer, $terms): Company {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            if (! in_array($company->kyc_status, [KycStatus::Pending, KycStatus::Rejected, KycStatus::Suspended], true)) {
                throw BusinessRuleException::make('kyc_invalid_transition', ['status' => $company->kyc_status->label()]);
            }

            $old = $company->only(['kyc_status', 'credit_limit', 'payment_terms_days', 'customer_group_id', 'account_manager_id']);

            $company->forceFill([
                'kyc_status' => KycStatus::Approved,
                'kyc_reviewed_by' => $reviewer->id,
                'kyc_reviewed_at' => now(),
                'kyc_rejection_reason' => null,
            ] + $this->termsAttributes($terms))->save();

            $company->documents()
                ->where('status', DocumentStatus::Pending->value)
                ->update(['status' => DocumentStatus::Verified->value, 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()]);

            AuditLog::record('kyc.approved', $company, $old, $company->only(array_keys($old)));

            return $company;
        });

        $this->notifyBuyerAdmins($company, new KycApprovedNotification($company));

        return $company;
    }

    public function reject(Company $company, User $reviewer, string $reason): Company
    {
        $company = DB::transaction(function () use ($company, $reviewer, $reason): Company {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            if ($company->kyc_status !== KycStatus::Pending) {
                throw BusinessRuleException::make('kyc_invalid_transition', ['status' => $company->kyc_status->label()]);
            }

            $company->forceFill([
                'kyc_status' => KycStatus::Rejected,
                'kyc_reviewed_by' => $reviewer->id,
                'kyc_reviewed_at' => now(),
                'kyc_rejection_reason' => $reason,
            ])->save();

            AuditLog::record('kyc.rejected', $company, [], ['reason' => $reason]);

            return $company;
        });

        $this->notifyBuyerAdmins($company, new KycRejectedNotification($company));

        return $company;
    }

    public function suspend(Company $company, User $reviewer, string $reason): Company
    {
        $company = DB::transaction(function () use ($company, $reviewer, $reason): Company {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            if ($company->kyc_status !== KycStatus::Approved) {
                throw BusinessRuleException::make('kyc_invalid_transition', ['status' => $company->kyc_status->label()]);
            }

            $company->forceFill([
                'kyc_status' => KycStatus::Suspended,
                'kyc_reviewed_by' => $reviewer->id,
                'kyc_reviewed_at' => now(),
            ])->save();

            AuditLog::record('kyc.suspended', $company, [], ['reason' => $reason]);

            return $company;
        });

        $this->notifyBuyerAdmins($company, new KycSuspendedNotification($company, $reason));

        return $company;
    }

    /** Credit line & commercial terms (credit.manage). Row-locked so it never races ledger updates. */
    public function updateTerms(Company $company, User $actor, array $terms): Company
    {
        return DB::transaction(function () use ($company, $terms): Company {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->termsAttributes($terms);
            $old = $company->only(array_keys($attributes));

            $company->forceFill($attributes)->save();
            AuditLog::record('company.terms_updated', $company, $old, $attributes);

            return $company;
        });
    }

    public function reviewDocument(CompanyDocument $document, User $reviewer, DocumentStatus $status, ?string $notes): CompanyDocument
    {
        $document->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ])->save();

        AuditLog::record('kyc.document_reviewed', $document->company, [], [
            'document_id' => $document->id,
            'status' => $status->value,
        ]);

        return $document;
    }

    /**
     * Buyer uploads replacement/additional documents. A rejected company returns to the review queue.
     *
     * @param  array<string, UploadedFile>  $documents
     */
    public function submitDocuments(Company $company, User $uploader, array $documents): Company
    {
        try {
            $company = DB::transaction(function () use ($company, $uploader, $documents): Company {
                $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

                if (! in_array($company->kyc_status, [KycStatus::Pending, KycStatus::Rejected], true)) {
                    throw BusinessRuleException::make('kyc_documents_locked');
                }

                foreach ($documents as $type => $file) {
                    $this->storage->store($company, $file, DocumentType::from($type), $uploader);
                }

                $company->forceFill([
                    'kyc_status' => KycStatus::Pending,
                    'kyc_submitted_at' => now(),
                ])->save();

                AuditLog::record('kyc.resubmitted', $company, [], ['types' => array_keys($documents)]);

                return $company;
            });
        } catch (Throwable $e) {
            $this->storage->rollback();

            throw $e;
        }

        $this->storage->commit();

        $reviewers = $company->accountManager()->exists()
            ? collect([$company->accountManager])
            : User::query()->staff()->active()->permission('kyc.review')->get();
        Notification::send($reviewers, new KycSubmittedNotification($company));

        return $company;
    }

    private function termsAttributes(array $terms): array
    {
        $map = ['credit_limit', 'payment_terms_days', 'customer_group_id', 'account_manager_id', 'credit_enabled', 'credit_on_hold'];

        $attributes = array_intersect_key($terms, array_flip($map));

        if (isset($attributes['credit_limit']) && ! array_key_exists('credit_enabled', $attributes)) {
            $attributes['credit_enabled'] = Money::isPositive((string) $attributes['credit_limit']);
        }

        return $attributes;
    }

    private function notifyBuyerAdmins(Company $company, object $notification): void
    {
        $admins = $company->users()->active()->role(Role::BuyerAdmin->value)->get();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification);
        }
    }
}
