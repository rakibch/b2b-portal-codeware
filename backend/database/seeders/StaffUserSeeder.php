<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = DatabaseSeeder::seedPassword();

        $staff = [
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@example.com'), 'name' => 'Super Admin', 'phone' => '01700000001', 'designation' => 'Managing Director', 'role' => Role::SuperAdmin, 'locale' => 'en'],
            ['email' => 'sales@example.com', 'name' => 'Rahim Uddin', 'phone' => '01700000002', 'designation' => 'Key Account Manager', 'role' => Role::SalesManager, 'locale' => 'bn'],
            ['email' => 'warehouse@example.com', 'name' => 'Karim Hossain', 'phone' => '01700000003', 'designation' => 'Dispatch Supervisor', 'role' => Role::WarehouseStaff, 'locale' => 'bn'],
        ];

        // Outside production only the super admin is seeded; other staff are created via the admin UI.
        if (app()->isProduction()) {
            $staff = [$staff[0]];
        }

        foreach ($staff as $data) {
            $user = User::query()->withTrashed()->firstOrNew(['email' => $data['email']]);
            $isNew = ! $user->exists;

            $user->fill([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'designation' => $data['designation'],
                'locale' => $data['locale'],
            ]);

            if ($isNew) {
                $user->password = $password;
            }

            $user->forceFill([
                'user_type' => UserType::Staff,
                'company_id' => null,
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'deleted_at' => null,
            ])->save();

            $user->syncRoles([$data['role']->value]);

            if ($isNew) {
                $this->command?->info("Seeded {$data['role']->value}: {$data['email']}");
            }
        }

        if (app()->isProduction() && env('SEED_DEFAULT_PASSWORD') === null) {
            $this->command?->warn('Generated super admin password (shown once, change after first login): '.$password);
        }
    }
}
