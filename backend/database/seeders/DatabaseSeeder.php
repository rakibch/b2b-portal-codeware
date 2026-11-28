<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingSeeder::class,
            CustomerGroupSeeder::class,
            CatalogSeeder::class,
            StaffUserSeeder::class,
        ]);

        // Demo buyer data is never seeded into production.
        if (! app()->isProduction()) {
            $this->call([
                DemoBuyerSeeder::class,
                AnnouncementSeeder::class,
            ]);
        }
    }

    /**
     * Seed password resolution. Production: SEED_DEFAULT_PASSWORD must be set, otherwise a strong
     * random password is generated and printed ONCE. Non-production falls back to a known dev password.
     */
    public static function seedPassword(): string
    {
        static $password = null;

        if ($password !== null) {
            return $password;
        }

        $fromEnv = env('SEED_DEFAULT_PASSWORD');

        if (is_string($fromEnv) && strlen($fromEnv) >= 12) {
            return $password = $fromEnv;
        }

        return $password = app()->isProduction() ? Str::password(20) : 'Password@123';
    }
}
