<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Support\Permissions;
use Tests\TestCase;

abstract class AdminTestCase extends TestCase
{
    protected function superAdmin(): User
    {
        return $this->makeUser(Permissions::SUPER_ADMIN);
    }

    protected function admin(): User
    {
        return $this->makeUser(Permissions::ADMIN);
    }

    protected function moderator(): User
    {
        return $this->makeUser(Permissions::MODERATOR);
    }

    /** A complete listing (passes submission requirements) owned by a business owner. */
    protected function completeBusiness(BusinessStatus $status = BusinessStatus::Pending, ?User $owner = null, array $attributes = []): Business
    {
        $owner ??= $this->makeUser(Permissions::BUSINESS_OWNER);

        return Business::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'category_id' => Category::query()->where('slug', 'cafes')->value('id'),
            'municipality_id' => Municipality::query()->where('slug', 'doha')->value('id'),
            'phone' => '+97444000000',
            'status' => $status,
            'submitted_at' => now(),
            'is_demo' => false,
        ], $attributes));
    }
}
