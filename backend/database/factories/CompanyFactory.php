<?php

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Enums\KycStatus;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'business_type' => fake()->randomElement(BusinessType::cases()),
            'contact_person' => fake()->name(),
            'email' => fake()->unique()->companyEmail(),
            'phone' => '01'.fake()->numerify('#########'),
            'trade_license_no' => 'TRAD/DNCC/'.fake()->unique()->numerify('######').'/'.now()->year,
            'tin_no' => fake()->unique()->numerify('############'),
            'bin_no' => fake()->unique()->numerify('#########-####'),
            'kyc_status' => KycStatus::Pending,
            'kyc_submitted_at' => now(),
            'credit_limit' => 0,
            'payment_terms_days' => 0,
            'outstanding_balance' => 0,
            'credit_enabled' => false,
        ];
    }

    public function approved(string $creditLimit = '500000.00', int $termsDays = 30): static
    {
        return $this->state(fn () => [
            'kyc_status' => KycStatus::Approved,
            'kyc_reviewed_at' => now(),
            'credit_limit' => $creditLimit,
            'payment_terms_days' => $termsDays,
            'credit_enabled' => true,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'kyc_status' => KycStatus::Rejected,
            'kyc_reviewed_at' => now(),
            'kyc_rejection_reason' => 'Trade license expired.',
        ]);
    }
}
