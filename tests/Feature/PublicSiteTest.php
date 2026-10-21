<?php

namespace Tests\Feature;

use App\Models\Business;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    public function test_arabic_homepage_loads_in_rtl(): void
    {
        $this->get('/')->assertOk()->assertSee('dir="rtl"', false)->assertSee('lang="ar"', false);
    }

    public function test_english_homepage_loads_in_ltr(): void
    {
        $this->get('/en')->assertOk()->assertSee('dir="ltr"', false)->assertSee('Discover');
    }

    public function test_language_switch_preserves_path_and_query(): void
    {
        $business = Business::factory()->published()->create(['slug' => 'pearl-cafe']);

        $this->get('/business/pearl-cafe')->assertOk()->assertSee(url('/en/business/pearl-cafe'), false);
        $this->get('/en/search?q=coffee&verified=1')->assertOk()->assertSee(url('/search').'?q=coffee&amp;verified=1', false);
    }
}
