<?php

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '01'.fake()->numerify('#########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'locale' => 'en',
            'user_type' => UserType::Buyer,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['user_type' => UserType::Staff, 'company_id' => null]);
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['user_type' => UserType::Buyer, 'company_id' => $company->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
