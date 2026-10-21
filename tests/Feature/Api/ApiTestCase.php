<?php

namespace Tests\Feature\Api;

use App\Models\Business;
use App\Models\Category;
use App\Models\Municipality;
use App\Models\User;
use App\Support\Permissions;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    protected function publishedBusiness(array $attributes = []): Business
    {
        return Business::factory()->published()->create(array_merge([
            'category_id' => Category::query()->where('slug', 'cafes')->value('id'),
            'municipality_id' => Municipality::query()->where('slug', 'doha')->value('id'),
        ], $attributes));
    }

    protected function owner(array $attributes = []): User
    {
        return $this->makeUser(Permissions::BUSINESS_OWNER, $attributes);
    }

    /** Clears cached guard state between requests made in one test. */
    protected function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
