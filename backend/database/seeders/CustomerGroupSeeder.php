<?php

namespace Database\Seeders;

use App\Models\CustomerGroup;
use Illuminate\Database\Seeder;

class CustomerGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['code' => 'silver', 'name' => ['en' => 'Silver', 'bn' => 'সিলভার'], 'description' => ['en' => 'Standard wholesale buyers', 'bn' => 'সাধারণ পাইকারি ক্রেতা'], 'discount_percent' => '0.00', 'priority' => 10],
            ['code' => 'gold', 'name' => ['en' => 'Gold', 'bn' => 'গোল্ড'], 'description' => ['en' => 'High-volume buyers with 2% extra discount', 'bn' => 'অধিক পরিমাণ ক্রেতা, অতিরিক্ত ২% ছাড়'], 'discount_percent' => '2.00', 'priority' => 20],
            ['code' => 'distributor', 'name' => ['en' => 'Distributor', 'bn' => 'পরিবেশক'], 'description' => ['en' => 'Authorised regional distributors', 'bn' => 'অনুমোদিত আঞ্চলিক পরিবেশক'], 'discount_percent' => '5.00', 'priority' => 30],
        ];

        foreach ($groups as $group) {
            CustomerGroup::query()->updateOrCreate(['code' => $group['code']], $group + ['is_active' => true]);
        }
    }
}
