<?php

namespace Database\Seeders;

use App\Domain\People\Enums\PersonStatus;
use App\Domain\People\Models\Person;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,
            InventorySeeder::class,
        ]);

        // A super admin who can log in to either domain (Phase 01 acceptance).
        // Local/dev convenience seed — production provisions the real owner.
        $person = Person::firstOrCreate(
            ['email' => 'super-admin@example.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'status' => PersonStatus::StaffActive,
                'hire_date' => now(),
                'email_verified_at' => now(),
            ],
        );

        $person->syncRoles('super_admin');

        // Local/dev demo data only (never in production).
        if (! app()->isProduction()) {
            $this->call(SampleDataSeeder::class);
        }
    }
}
