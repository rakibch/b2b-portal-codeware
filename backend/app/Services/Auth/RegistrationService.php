<?php

namespace App\Services\Auth;

use App\Enums\AddressType;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Enums\Role;
use App\Enums\UserType;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Notifications\KycSubmittedNotification;
use App\Services\Kyc\KycDocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class RegistrationService
{
    public function __construct(private readonly KycDocumentStorage $storage) {}

    /**
     * Creates a PENDING company, its Buyer Admin, addresses and KYC documents atomically.
     * Uploaded files are removed again if the database transaction fails.
     *
     * @param  array<string, mixed>  $data  validated RegisterRequest payload
     * @param  array<string, UploadedFile>  $documents  document type => file
     */
    public function register(array $data, array $documents): User
    {
        try {
            $user = DB::transaction(function () use ($data, $documents): User {
                $company = new Company($data['company']);
                $company->forceFill([
                    'kyc_status' => KycStatus::Pending,
                    'kyc_submitted_at' => now(),
                    'credit_limit' => '0.00',
                    'payment_terms_days' => 0,
                    'outstanding_balance' => '0.00',
                    'credit_enabled' => false,
                ])->save();

                $company->addresses()->create($data['billing_address'] + [
                    'type' => AddressType::Billing,
                    'label' => $data['billing_address']['label'] ?? 'Billing',
                    'is_default' => true,
                ]);

                foreach (array_values($data['delivery_addresses']) as $i => $address) {
                    $company->addresses()->create($address + [
                        'type' => AddressType::Shipping,
                        'is_default' => $i === 0,
                    ]);
                }

                $user = new User([
                    'name' => $data['user']['name'],
                    'email' => $data['user']['email'],
                    'phone' => $data['user']['phone'] ?? null,
                    'designation' => $data['user']['designation'] ?? null,
                    'password' => $data['user']['password'],
                    'locale' => $data['user']['locale'] ?? app()->getLocale(),
                ]);
                $user->forceFill([
                    'user_type' => UserType::Buyer,
                    'company_id' => $company->id,
                    'is_active' => true,
                ])->save();
                $user->assignRole(Role::BuyerAdmin->value);

                foreach ($documents as $type => $file) {
                    $this->storage->store($company, $file, DocumentType::from($type), $user);
                }

                AuditLog::record('company.registered', $company, [], ['name' => $company->name, 'user_id' => $user->id]);

                return $user->setRelation('company', $company);
            });
        } catch (Throwable $e) {
            $this->storage->rollback();

            throw $e;
        }

        $this->storage->commit();
        $this->notifyReviewers($user->company);

        return $user;
    }

    private function notifyReviewers(Company $company): void
    {
        $reviewers = User::query()->staff()->active()->permission('kyc.review')->get();

        if ($reviewers->isNotEmpty()) {
            Notification::send($reviewers, new KycSubmittedNotification($company));
        }
    }
}
