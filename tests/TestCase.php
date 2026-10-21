<?php

namespace Tests;

use App\Models\User;
use App\Support\Permissions;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Reference data every test can rely on. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, LocationSeeder::class, CategorySeeder::class, ContentSeeder::class]);
        $this->withoutVite();
    }

    protected function makeUser(string $role = Permissions::USER, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
