<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Database\Seeder;

/**
 * Seeds REFERENCE data only (roles, locations, categories, pages, FAQs).
 * Sample businesses are never seeded here — run DemoDataSeeder explicitly
 * in local environments: php artisan db:seed --class=DemoDataSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LocationSeeder::class,
            CategorySeeder::class,
            ContentSeeder::class,
        ]);

        // Optional first administrator from environment variables (never hard-coded).
        $email = env('SEED_ADMIN_EMAIL');
        $password = env('SEED_ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::query()->firstOrCreate(['email' => mb_strtolower($email)], [
                'name' => env('SEED_ADMIN_NAME', 'Daleel Administrator'),
                'password' => $password,
                'locale' => 'ar',
            ]);
            $admin->forceFill(['email_verified_at' => $admin->email_verified_at ?? now()])->save();
            $admin->syncRoles([Permissions::SUPER_ADMIN]);
            $this->command?->info("Super administrator ready: {$email}");
        } else {
            $this->command?->warn('No administrator seeded. Run `php artisan daleel:create-admin` to create one.');
        }
    }
}
