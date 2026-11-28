<?php

namespace Database\Seeders;

use App\Enums\AddressType;
use App\Enums\BusinessType;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Enums\LedgerEntryType;
use App\Enums\Role;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\CompanyDocument;
use App\Models\CompanyProductPrice;
use App\Models\CustomerGroup;
use App\Models\LedgerEntry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Development/demo data only — never runs in production (see DatabaseSeeder). */
class DemoBuyerSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $admin = User::query()->where('email', env('SEED_ADMIN_EMAIL', 'admin@example.com'))->firstOrFail();
            $sales = User::query()->where('email', 'sales@example.com')->firstOrFail();

            $this->seedApprovedCompany($admin, $sales);
            $this->seedPendingCompany();
        });
    }

    private function seedApprovedCompany(User $admin, User $sales): void
    {
        $company = $this->upsertCompany([
            'name' => 'Dhaka Super Mart Ltd.',
            'trade_name' => 'Dhaka Super Mart',
            'business_type' => BusinessType::SuperShop,
            'contact_person' => 'Tanvir Ahmed',
            'email' => 'procurement@dhakasupermart.example.com',
            'phone' => '01811000001',
            'trade_license_no' => 'TRAD/DNCC/045871/2026',
            'tin_no' => '458712365478',
            'bin_no' => '004587123-0102',
            'website' => 'https://dhakasupermart.example.com',
        ]);

        $company->forceFill([
            'kyc_status' => KycStatus::Approved,
            'kyc_submitted_at' => now()->subDays(20),
            'kyc_reviewed_by' => $admin->id,
            'kyc_reviewed_at' => now()->subDays(18),
            'customer_group_id' => CustomerGroup::query()->where('code', 'gold')->value('id'),
            'account_manager_id' => $sales->id,
            'credit_limit' => '500000.00',
            'payment_terms_days' => 30,
            'credit_enabled' => true,
            'credit_on_hold' => false,
        ])->save();

        $this->seedAddresses($company, [
            ['type' => AddressType::Billing, 'label' => 'Head Office', 'contact_name' => 'Tanvir Ahmed', 'contact_phone' => '01811000001', 'address_line1' => 'Level 5, 32 Kemal Ataturk Avenue', 'area' => 'Banani', 'city' => 'Dhaka', 'district' => 'Dhaka', 'postal_code' => '1213', 'is_default' => true],
            ['type' => AddressType::Shipping, 'label' => 'Mirpur Central Warehouse', 'contact_name' => 'Jamal Mia', 'contact_phone' => '01811000002', 'address_line1' => 'Plot 14, Section 7', 'area' => 'Mirpur', 'city' => 'Dhaka', 'district' => 'Dhaka', 'postal_code' => '1216', 'is_default' => true],
            ['type' => AddressType::Shipping, 'label' => 'Uttara Outlet', 'contact_name' => 'Sharmin Akter', 'contact_phone' => '01811000003', 'address_line1' => 'House 22, Road 11, Sector 4', 'area' => 'Uttara', 'city' => 'Dhaka', 'district' => 'Dhaka', 'postal_code' => '1230', 'is_default' => false],
        ]);

        $this->seedDocument($company, DocumentType::TradeLicense, DocumentStatus::Verified, $admin);

        $buyerAdmin = $this->upsertBuyer($company, 'buyer@example.com', 'Tanvir Ahmed', '01811000001', 'Head of Procurement', Role::BuyerAdmin, null, 'en');
        $this->upsertBuyer($company, 'procurement@example.com', 'Nusrat Jahan', '01811000004', 'Procurement Officer', Role::BuyerStaff, '50000.00', 'bn', $buyerAdmin);

        // Contract price override (highest precedence in PricingEngineService).
        $soybean = Product::query()->where('sku', 'OIL-SOY-5L')->firstOrFail();
        CompanyProductPrice::query()->updateOrCreate(
            ['company_id' => $company->id, 'product_id' => $soybean->id],
            ['unit_price' => '810.00', 'min_quantity' => 8, 'valid_from' => now()->subMonth()->toDateString(), 'valid_until' => now()->addMonths(6)->toDateString(), 'created_by' => $admin->id],
        );

        // Opening balance carried over from the legacy system.
        if (! $company->ledgerEntries()->exists()) {
            $opening = '45000.00';

            LedgerEntry::query()->forceCreate([
                'company_id' => $company->id,
                'entry_date' => now()->subDays(18)->toDateString(),
                'type' => LedgerEntryType::OpeningBalance,
                'reference' => 'OB-'.$company->id,
                'description' => ['en' => 'Opening balance brought forward', 'bn' => 'প্রারম্ভিক জের আনীত'],
                'debit' => $opening,
                'credit' => '0.00',
                'balance' => $opening,
                'created_by' => $admin->id,
            ]);

            $company->forceFill(['outstanding_balance' => $opening])->save();
        }
    }

    private function seedPendingCompany(): void
    {
        $company = $this->upsertCompany([
            'name' => 'Chattogram Traders',
            'trade_name' => null,
            'business_type' => BusinessType::Wholesaler,
            'contact_person' => 'Mohammad Faruk',
            'email' => 'info@ctgtraders.example.com',
            'phone' => '01911000001',
            'trade_license_no' => 'TRAD/CCC/018734/2026',
            'tin_no' => '187341256987',
            'bin_no' => null,
            'website' => null,
        ]);

        $company->forceFill([
            'kyc_status' => KycStatus::Pending,
            'kyc_submitted_at' => now()->subDay(),
            'credit_limit' => '0.00',
            'payment_terms_days' => 0,
            'credit_enabled' => false,
        ])->save();

        $this->seedAddresses($company, [
            ['type' => AddressType::Billing, 'label' => 'Office', 'contact_name' => 'Mohammad Faruk', 'contact_phone' => '01911000001', 'address_line1' => '45 Khatunganj Road', 'area' => 'Khatunganj', 'city' => 'Chattogram', 'district' => 'Chattogram', 'postal_code' => '4000', 'is_default' => true],
            ['type' => AddressType::Shipping, 'label' => 'Khatunganj Godown', 'contact_name' => 'Abdul Kader', 'contact_phone' => '01911000002', 'address_line1' => '47 Khatunganj Road', 'area' => 'Khatunganj', 'city' => 'Chattogram', 'district' => 'Chattogram', 'postal_code' => '4000', 'is_default' => true],
        ]);

        $this->seedDocument($company, DocumentType::TradeLicense, DocumentStatus::Pending);
        $this->seedDocument($company, DocumentType::Nid, DocumentStatus::Pending);

        $this->upsertBuyer($company, 'pending.buyer@example.com', 'Mohammad Faruk', '01911000001', 'Proprietor', Role::BuyerAdmin, null, 'bn');
    }

    private function upsertCompany(array $attributes): Company
    {
        $company = Company::query()->withTrashed()->firstOrNew(['trade_license_no' => $attributes['trade_license_no']]);
        $company->fill($attributes);
        $company->forceFill(['deleted_at' => null])->save();

        return $company;
    }

    private function seedAddresses(Company $company, array $addresses): void
    {
        foreach ($addresses as $address) {
            $company->addresses()->updateOrCreate(
                ['type' => $address['type']->value, 'label' => $address['label']],
                $address + ['country_code' => 'BD'],
            );
        }
    }

    private function upsertBuyer(
        Company $company,
        string $email,
        string $name,
        string $phone,
        string $designation,
        Role $role,
        ?string $approvalLimit,
        string $locale,
        ?User $invitedBy = null,
    ): User {
        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
        $isNew = ! $user->exists;

        $user->fill(['name' => $name, 'phone' => $phone, 'designation' => $designation, 'locale' => $locale]);

        if ($isNew) {
            $user->password = DatabaseSeeder::seedPassword();
        }

        $user->forceFill([
            'user_type' => UserType::Buyer,
            'company_id' => $company->id,
            'is_active' => true,
            'order_approval_limit' => $approvalLimit,
            'invited_by' => $invitedBy?->id,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deleted_at' => null,
        ])->save();

        $user->syncRoles([$role->value]);

        return $user;
    }

    /** Writes a tiny valid PDF to the PRIVATE kyc disk so the admin review flow has a real file. */
    private function seedDocument(Company $company, DocumentType $type, DocumentStatus $status, ?User $reviewer = null): void
    {
        $disk = config('b2b.kyc.disk');
        $path = "kyc/{$company->public_id}/{$type->value}-sample.pdf";
        $content = $this->samplePdf("{$company->name} - {$type->value}");

        Storage::disk($disk)->put($path, $content);

        $document = CompanyDocument::query()->firstOrNew(['company_id' => $company->id, 'type' => $type->value]);
        $document->fill([
            'disk' => $disk,
            'path' => $path,
            'original_name' => "{$type->value}.pdf",
            'mime_type' => 'application/pdf',
            'size' => strlen($content),
            'checksum' => hash('sha256', $content),
        ]);
        $document->forceFill([
            'status' => $status,
            'reviewed_by' => $reviewer?->id,
            'reviewed_at' => $reviewer ? now()->subDays(18) : null,
        ]);
        $company->documents()->save($document);
    }

    private function samplePdf(string $text): string
    {
        $text = preg_replace('/[^A-Za-z0-9 .\-]/', '', $text);
        $stream = "BT /F1 18 Tf 72 720 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
