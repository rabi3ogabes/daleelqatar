<?php

namespace Tests\Feature\Growth;

use App\Models\Area;
use App\Models\Business;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Municipality;
use App\Models\SeasonalPeriod;
use App\Notifications\SeasonHoursReminder;
use App\Services\SettingsService;
use App\Support\Guides\GuideBuilder;
use App\Support\Permissions;
use App\Support\Search\SearchInterpreter;
use App\Support\Seasons\SeasonReminders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SeasonsGuidesSearchTest extends TestCase
{
    private function listing(array $attributes = []): Business
    {
        return Business::factory()->published()->create(array_merge(['is_demo' => false, 'owner_id' => $this->makeUser(Permissions::BUSINESS_OWNER)->id], $attributes));
    }

    private function ramadanNow(): SeasonalPeriod
    {
        SeasonalPeriod::query()->update(['is_active' => false]);

        return SeasonalPeriod::query()->create([
            'slug' => 'ramadan-test', 'name_ar' => 'رمضان', 'name_en' => 'Ramadan', 'kind' => 'ramadan',
            'starts_on' => now()->subDays(3)->toDateString(), 'ends_on' => now()->addDays(20)->toDateString(), 'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- seasons

    public function test_special_hours_replace_regular_hours_while_the_season_runs(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 13:00', 'Asia/Qatar')); // Wednesday afternoon
        $season = $this->ramadanNow();
        $business = $this->listing();
        $business->openingHours()->createMany(collect(range(0, 6))->map(fn ($d) => ['day_of_week' => $d, 'opens_at' => '09:00', 'closes_at' => '17:00'])->all());

        $this->assertTrue($business->fresh()->isOpenNow());
        $this->assertSame(1, Business::query()->openNow()->whereKey($business->id)->count());

        // Owner sets Ramadan hours: evenings only.
        $owner = $business->owner;
        $hours = collect(range(0, 6))->mapWithKeys(fn ($d) => [$d => ['periods' => [['opens' => '20:00', 'closes' => '02:00']]]])->all();
        $this->actingAs($owner)->put('/en/account/businesses/'.$business->uuid.'/special-hours/'.$season->id, ['mode' => 'custom', 'hours' => $hours])->assertRedirect();

        $fresh = $business->fresh();
        $this->assertSame($season->id, $fresh->currentSeason()?->id);
        $this->assertFalse($fresh->isOpenNow());
        $this->assertSame(0, Business::query()->openNow()->whereKey($business->id)->count());

        $this->travelTo(CarbonImmutable::parse('2026-11-04 23:30', 'Asia/Qatar'));
        $this->assertTrue($business->fresh()->isOpenNow());
        $this->assertSame(1, Business::query()->openNow()->whereKey($business->id)->count());

        $this->get('/en/business/'.$business->slug)->assertOk()->assertSee('Ramadan hours')->assertSee('specialOpeningHoursSpecification', false);

        // Back to regular hours for the season.
        $this->actingAs($owner)->put('/en/account/businesses/'.$business->uuid.'/special-hours/'.$season->id, ['mode' => 'regular'])->assertRedirect();
        $this->assertNull($business->fresh()->currentSeason());
    }

    public function test_closed_for_the_season_and_access_rules(): void
    {
        $season = $this->ramadanNow();
        $business = $this->listing();

        $this->actingAs($business->owner)->get('/en/account/businesses/'.$business->uuid.'/special-hours')->assertOk()->assertSee('Ramadan');
        $this->actingAs($business->owner)->put('/en/account/businesses/'.$business->uuid.'/special-hours/'.$season->id, ['mode' => 'closed'])->assertRedirect();
        $this->assertSame(7, $business->specialHours()->where('is_closed', true)->count());
        $this->get('/en/business/'.$business->slug)->assertSee('Closed during Ramadan');

        $this->actingAs($this->makeUser(Permissions::BUSINESS_OWNER))->get('/en/account/businesses/'.$business->uuid.'/special-hours')->assertNotFound();
        $this->actingAs($this->makeUser(Permissions::ADMIN))->get('/en/admin/businesses/'.$business->id.'/special-hours')->assertOk();
    }

    public function test_admin_manages_seasons_and_owners_get_one_reminder(): void
    {
        Notification::fake();
        $admin = $this->makeUser(Permissions::ADMIN);
        $this->actingAs($admin)->get('/en/admin/seasons')->assertOk()->assertSee('Ramadan 2027');
        $this->actingAs($admin)->post('/en/admin/seasons', [
            'name_ar' => 'عيد الفطر', 'name_en' => 'Eid al-Fitr', 'kind' => 'eid',
            'starts_on' => now()->addDays(5)->toDateString(), 'ends_on' => now()->addDays(8)->toDateString(), 'is_active' => '1',
        ])->assertRedirect('/en/admin/seasons');
        $this->actingAs($admin)->post('/en/admin/seasons', ['name_ar' => 'x', 'name_en' => 'x', 'kind' => 'eid', 'starts_on' => '2027-01-10', 'ends_on' => '2027-01-01'])
            ->assertSessionHasErrors('ends_on');

        $business = $this->listing();
        $this->assertSame(1, app(SeasonReminders::class)->send());
        $this->assertSame(0, app(SeasonReminders::class)->send());
        Notification::assertSentTo($business->owner, SeasonHoursReminder::class);

        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get('/en/admin/seasons')->assertForbidden();
    }

    // ----------------------------------------------------------------- guides

    public function test_guide_lists_only_complete_listings_with_pins_first(): void
    {
        $category = Category::query()->where('slug', 'pharmacies')->firstOrFail();
        $doha = Municipality::query()->where('slug', 'doha')->firstOrFail();
        $long = str_repeat('A trusted neighbourhood pharmacy with a pharmacist on duty. ', 3);
        $a = $this->listing(['category_id' => $category->id, 'municipality_id' => $doha->id, 'description_en' => $long, 'name_en' => 'Alpha Pharmacy']);
        $b = $this->listing(['category_id' => $category->id, 'municipality_id' => $doha->id, 'description_en' => $long, 'name_en' => 'Beta Pharmacy', 'is_verified' => true]);
        $c = $this->listing(['category_id' => $category->id, 'municipality_id' => $doha->id, 'description_en' => $long, 'name_en' => 'Gamma Pharmacy']);
        $thin = $this->listing(['category_id' => $category->id, 'municipality_id' => $doha->id, 'description_en' => null, 'description_ar' => null, 'email' => null, 'name_en' => 'Thin Pharmacy']);

        $admin = $this->makeUser(Permissions::ADMIN);
        $this->actingAs($admin)->getJson('/en/admin/guides/preview?category_id='.$category->id.'&municipality_id='.$doha->id)->assertOk()->assertJson(['count' => 3]);
        $this->actingAs($admin)->get('/en/admin/guides/create?category_id='.$category->id.'&municipality_id='.$doha->id)->assertOk()->assertSee('Pharmacies in Doha');

        $intro = str_repeat('Pharmacies in Doha that keep their details complete and up to date on the directory. ', 3);
        $this->actingAs($admin)->post('/en/admin/guides', [
            'title_ar' => 'صيدليات في الدوحة', 'title_en' => 'Pharmacies in Doha', 'intro_ar' => $intro, 'intro_en' => $intro,
            'category_id' => $category->id, 'municipality_id' => $doha->id, 'max_items' => 12, 'status' => 'published', 'pinned' => [$c->id],
        ])->assertRedirect();
        $guide = Guide::query()->sole();

        $html = $this->get('/en/guides/'.$guide->slug)->assertOk()->assertSee('ItemList', false)->assertDontSee('Thin Pharmacy')->getContent();
        $this->assertTrue(strpos($html, 'Gamma Pharmacy') < strpos($html, 'Beta Pharmacy'), 'pinned first');
        $this->assertTrue(strpos($html, 'Beta Pharmacy') < strpos($html, 'Alpha Pharmacy'), 'verified before unverified');
        $builder = app(GuideBuilder::class);
        $this->assertTrue($builder->indexable($guide, $builder->listings($guide)->count()));
        $this->get('/en/guides')->assertOk()->assertSee('Pharmacies in Doha');
        $this->get('/sitemaps/pages.xml')->assertOk()->assertSee('/en/guides/'.$guide->slug, false);

        // Too few listings or no introduction: the page stays out of search engines.
        $guide->update(['intro_en' => 'Short.', 'intro_ar' => 'قصير']);
        $this->assertFalse($builder->indexable($guide->fresh(), 3));
        $this->assertFalse($builder->indexable($guide->fresh(), 2));

        $this->actingAs($this->makeUser(Permissions::MODERATOR))->get('/en/admin/guides')->assertForbidden();
    }

    public function test_draft_guides_are_hidden_from_visitors(): void
    {
        $guide = Guide::query()->forceCreate(['slug' => 'draft-guide', 'title_ar' => 'مسودة', 'title_en' => 'Draft', 'status' => 'draft', 'max_items' => 12]);

        $this->get('/en/guides/draft-guide')->assertNotFound();
        $this->actingAs($this->makeUser(Permissions::ADMIN))->get('/en/guides/draft-guide')->assertOk()->assertSee(__('guides.draft_preview', [], 'en'));
    }

    // ----------------------------------------------------------- smart search

    public function test_plain_sentences_become_filters_in_both_languages(): void
    {
        $location = (string) $this->get('/en/search?q='.urlencode('pharmacy open now in Al Wakrah'))->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString('/en/search?category=pharmacies&municipality=al-wakrah&open_now=1&understood=', $location);
        $this->get($location)->assertOk()->assertSee(__('search.smart.understood', [], 'en'))->assertSee('exact=1', false);

        $response = $this->get('/search?q='.urlencode('صيدلية مفتوحة الآن في الوكرة'));
        $response->assertRedirect();
        $this->assertStringContainsString('category=pharmacies', $response->headers->get('Location'));
        $this->assertStringContainsString('municipality=al-wakrah', $response->headers->get('Location'));
        $this->assertStringContainsString('open_now=1', $response->headers->get('Location'));

        $area = Area::query()->where('slug', 'lusail')->firstOrFail();
        $dentist = $this->listing(['category_id' => Category::query()->where('slug', 'dental')->value('id'), 'municipality_id' => $area->municipality_id, 'area_id' => $area->id]);
        $location = $this->get('/en/search?q='.urlencode('kids dentist Lusail'))->headers->get('Location');
        $this->assertStringContainsString('category=dental', $location);
        $this->assertStringContainsString('area=lusail', $location);

        // "kids" matches nothing, so the understood filters alone are shown, with an explanation.
        $this->get($location)->assertOk()->assertSee($dentist->name_en)->assertSee('No listing matched');
    }

    public function test_business_names_and_exact_searches_are_left_alone(): void
    {
        $this->listing(['name_en' => 'Doha Bank Tower Branch', 'name_ar' => 'بنك الدوحة']);

        $this->get('/en/search?q='.urlencode('Doha Bank'))->assertOk();
        $this->get('/en/search?q='.urlencode('pharmacy in Al Wakrah').'&exact=1')->assertOk();
        $this->get('/en/search?q=pharmacy&category=pharmacies')->assertOk();

        app(SettingsService::class)->set('search.smart', false);
        $this->get('/en/search?q='.urlencode('pharmacy open now in Al Wakrah'))->assertOk();
    }

    public function test_ai_reads_sentences_the_rules_cannot_and_only_known_slugs_are_used(): void
    {
        $this->app->instance(SearchInterpreter::class, new class implements SearchInterpreter
        {
            public int $calls = 0;

            public function available(): bool
            {
                return true;
            }

            public function interpret(string $query, array $taxonomy, array $places): ?array
            {
                $this->calls++;

                return ['category' => 'car-repair', 'municipality' => 'no-such-place', 'area' => null, 'open_now' => false, 'keywords' => 'ac gas'];
            }
        });
        $ai = app(SearchInterpreter::class);

        $location = $this->get('/en/search?q='.urlencode('my car blows warm air need someone'))->headers->get('Location');
        $this->assertStringContainsString('category=car-repair', (string) $location);
        $this->assertStringNotContainsString('no-such-place', (string) $location);

        $this->get('/en/search?q='.urlencode('my car blows warm air need someone'));
        $this->assertSame(1, $ai->calls, 'answers are cached per wording');
    }
}
