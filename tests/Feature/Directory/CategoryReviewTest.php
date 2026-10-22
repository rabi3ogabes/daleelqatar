<?php

namespace Tests\Feature\Directory;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Category;
use App\Models\CategoryReview;
use App\Support\Categories\CategoryClassifier;
use App\Support\Categories\CategoryReviewer;
use App\Support\Enrichment\AiWriter;
use App\Support\Import\CategoryMatcher;
use App\Support\Permissions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CategoryReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeAiWriter::$categories = [];
    }

    /** Real mistakes found in the imported data, and where each listing belongs. */
    public static function listings(): array
    {
        return [
            'café filed as travel' => [['name_en' => 'TabiBoba Cafe', 'keywords' => 'Travel and Tourism, Coffee Shops and Cafe, Bubble tea store'], 'cafes'],
            'bakery filed as travel' => [['name_en' => 'Hype Bakery', 'keywords' => 'Travel and Tourism, Bakeries & Patisseries'], 'bakeries-sweets'],
            'shipping line filed as contractor' => [['name_en' => 'MAERSK LINE', 'keywords' => 'Marine & Offshore - Contractors, Marine Equipment & Supplies, Ship Builders & Repairers'], 'marine-shipping'],
            'public park' => [['name_en' => 'Al Rayyan Park', 'keywords' => 'Travel and Tourism, Public Parks, Park'], 'parks-attractions'],
            'embassy' => [['name_en' => 'Embassy of Japan', 'keywords' => 'Business, Embassies'], 'embassies-consulates'],
            'ministry' => [['name_en' => 'Ministry of Municipality & Urban Planning', 'keywords' => 'Business'], 'government-offices'],
            'juice stall listed under hotels' => [['name_en' => 'Al Showq Juice Stall', 'keywords' => 'Hotels, Restaurants'], 'cafes'],
            'kindergarten listed as school' => [['name_en' => 'Tiny Talents Kindergarten', 'keywords' => 'School, Employer, Education & Training'], 'nurseries'],
            'job-ad labels are ignored' => [['name_en' => 'Blue Star Air Conditioning', 'keywords' => 'Employer, accountant jobs Doha, Construction & Engineering'], 'ac-services'],
            'two-field label counts half' => [['name_en' => 'Texas A&M University at Qatar', 'keywords' => 'Schools & Universities, schools - colleges & universities'], 'universities'],
            'arabic name only' => [['name_en' => null, 'name_ar' => 'صيدلية الدوحة الحديثة', 'keywords' => null], 'pharmacies'],
            'legal suffix says little' => [['name_en' => 'Cabaco Chemical Trading Company', 'keywords' => 'Business'], 'chemicals'],
        ];
    }

    #[DataProvider('listings')]
    public function test_the_classifier_finds_the_right_category(array $attributes, string $expected): void
    {
        $business = new Business($attributes + ['keywords' => null]);
        $business->forceFill(['keywords' => $attributes['keywords'] ?? null, 'category_id' => $this->category('other-businesses')->id]);

        $verdict = app(CategoryClassifier::class)->classify($business);

        $this->assertSame($expected, $verdict['category'], json_encode(array_slice($verdict['scores'], 0, 4)));
        $this->assertNotEmpty($verdict['evidence']);
    }

    public function test_a_scan_proposes_corrections_and_confirms_correct_listings(): void
    {
        $wrong = $this->business('TabiBoba Cafe', 'travel-agencies', 'Travel and Tourism, Coffee Shops and Cafe, Bubble tea store');
        $right = $this->business('Doha Dental Clinic', 'dental', 'Dentists, Dental Clinics');
        $unknown = $this->business('Al Noor Est', 'other-businesses', 'Business');
        $parent = $this->business('Gulf Barber', 'beauty-salons', 'Barbers');

        $step = app(CategoryReviewer::class)->scan('unchecked', 0, useAi: false);

        $this->assertTrue($step['done']);
        $this->assertSame(4, $step['processed']);
        $this->assertSame(2, $step['suggested']);

        $review = CategoryReview::query()->where('business_id', $wrong->id)->first();
        $this->assertSame('pending', $review->status);
        $this->assertSame($this->category('cafes')->id, $review->suggested_category_id);
        $this->assertSame('fix', $review->kind);
        $this->assertSame('rules', $review->method);
        $this->assertContains('label', array_column($review->reasons, 'field'), 'the reasons cite the source categories');

        $this->assertSame('correct', CategoryReview::query()->where('business_id', $right->id)->value('status'));
        $this->assertNull(CategoryReview::query()->where('business_id', $unknown->id)->value('suggested_category_id'), 'nothing to go on: an admin decides');
        $this->assertSame('refine', CategoryReview::query()->where('business_id', $parent->id)->value('kind'), 'Beauty & Salons → Barbershops is a refinement');

        // Nothing changed on the listings yet.
        $this->assertSame($this->category('travel-agencies')->id, $wrong->fresh()->category_id);
        $this->assertSame(0, app(CategoryReviewer::class)->scope('unchecked')->count());
    }

    public function test_the_ai_gives_a_second_opinion_on_unclear_listings(): void
    {
        $this->app->instance(AiWriter::class, new FakeAiWriter);
        FakeAiWriter::$categories = ['Al Noor Est' => ['category' => 'perfumes-oud', 'confidence' => 88]];
        $business = $this->business('Al Noor Est', 'other-businesses', 'Business');

        app(CategoryReviewer::class)->scan('unchecked', 0, useAi: true);

        $review = CategoryReview::query()->where('business_id', $business->id)->first();
        $this->assertSame('ai', $review->method);
        $this->assertSame($this->category('perfumes-oud')->id, $review->suggested_category_id);
        $this->assertSame(83, $review->confidence);
        $this->assertSame('ai', $review->reasons[0]['field']);
        $this->assertSame('الاسم يوضح النشاط.', $review->reasons[0]['text_ar']);
    }

    public function test_admin_applies_a_correction_and_it_is_audited(): void
    {
        $business = $this->business('TabiBoba Cafe', 'travel-agencies', 'Travel and Tourism, Coffee Shops and Cafe, Bubble tea store');
        app(CategoryReviewer::class)->scan('unchecked', 0, useAi: false);
        $review = CategoryReview::query()->where('business_id', $business->id)->first();
        $admin = $this->makeUser(Permissions::ADMIN);

        $this->actingAs($admin)->get(route('admin.category-review.index'))
            ->assertOk()->assertSee('TabiBoba Cafe')->assertSee($this->category('cafes')->name_ar)->assertSee('Coffee Shops');

        $this->actingAs($admin)->postJson(route('admin.category-review.apply', $review))->assertOk()->assertJsonPath('message', __('category_review.applied_one', ['name' => 'TabiBoba Cafe']));

        $this->assertSame($this->category('cafes')->id, $business->fresh()->category_id);
        $this->assertSame('applied', $review->fresh()->status);
        $this->assertSame($admin->id, $review->fresh()->reviewed_by);
        $log = AuditLog::query()->where('action', 'business.category.changed')->latest('id')->first();
        $this->assertSame($this->category('travel-agencies')->id, $log->old_values['category_id']);
        $this->assertSame($this->category('cafes')->id, $log->new_values['category_id']);

        // A decided listing is never reopened by later scans.
        app(CategoryReviewer::class)->scan('all', 0, useAi: false);
        $this->assertSame('applied', $review->fresh()->status);
    }

    public function test_admin_can_pick_another_category_or_keep_the_current_one(): void
    {
        $a = $this->business('TabiBoba Cafe', 'travel-agencies', 'Travel and Tourism, Coffee Shops and Cafe');
        $b = $this->business('Hype Bakery', 'travel-agencies', 'Travel and Tourism, Bakeries & Patisseries');
        app(CategoryReviewer::class)->scan('unchecked', 0, useAi: false);
        $admin = $this->makeUser(Permissions::ADMIN);
        $ra = CategoryReview::query()->where('business_id', $a->id)->first();
        $rb = CategoryReview::query()->where('business_id', $b->id)->first();

        $this->actingAs($admin)->post(route('admin.category-review.apply', $ra), ['category_id' => $this->category('fast-food')->id])->assertRedirect();
        $this->assertSame($this->category('fast-food')->id, $a->fresh()->category_id);
        $this->assertTrue(AuditLog::query()->where('action', 'business.category.changed')->latest('id')->first()->metadata['overridden']);

        $this->actingAs($admin)->post(route('admin.category-review.keep', $rb))->assertRedirect();
        $this->assertSame('kept', $rb->fresh()->status);
        $this->assertSame($this->category('travel-agencies')->id, $b->fresh()->category_id);

        $this->actingAs($admin)->post(route('admin.category-review.apply', $ra), ['category_id' => 999999])->assertSessionHasErrors('category_id');
    }

    public function test_bulk_and_confident_corrections(): void
    {
        $listings = collect([
            ['TabiBoba Cafe', 'Travel and Tourism, Coffee Shops and Cafe, Bubble tea store'],
            ['Hype Bakery', 'Travel and Tourism, Bakeries & Patisseries'],
            ['Embassy of Japan', 'Business, Embassies'],
        ])->map(fn ($l) => $this->business($l[0], 'travel-agencies', $l[1]));
        app(CategoryReviewer::class)->scan('unchecked', 0, useAi: false);
        $admin = $this->makeUser(Permissions::ADMIN);
        $reviews = CategoryReview::query()->whereIn('business_id', $listings->pluck('id'))->get()->keyBy('business_id');

        // Bulk: apply the first, keep the second.
        $this->actingAs($admin)->post(route('admin.category-review.bulk'), ['action' => 'apply', 'ids' => [$reviews[$listings[0]->id]->id]])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.category-review.bulk'), ['action' => 'keep', 'ids' => [$reviews[$listings[1]->id]->id]])->assertSessionHas('success');
        $this->assertSame('applied', $reviews[$listings[0]->id]->fresh()->status);
        $this->assertSame('kept', $reviews[$listings[1]->id]->fresh()->status);

        // Everything confident that is left.
        $high = CategoryReview::query()->proposed()->where('confidence', '>=', CategoryReview::HIGH)->count();
        $this->actingAs($admin)->postJson(route('admin.category-review.apply-confident'))->assertOk()
            ->assertJsonPath('applied', $high)->assertJsonPath('done', true);
        $this->assertSame($this->category('embassies-consulates')->id, $listings[2]->fresh()->category_id);
    }

    public function test_changing_a_category_by_hand_closes_the_open_suggestion(): void
    {
        $business = $this->business('TabiBoba Cafe', 'travel-agencies', 'Travel and Tourism, Coffee Shops and Cafe');
        app(CategoryReviewer::class)->scan('unchecked', 0, useAi: false);

        $business->update(['category_id' => $this->category('cafes')->id]);

        $this->assertSame('kept', CategoryReview::query()->where('business_id', $business->id)->value('status'));
    }

    public function test_the_scan_endpoint_and_business_page_check(): void
    {
        $business = $this->business('Hype Bakery', 'travel-agencies', 'Travel and Tourism, Bakeries & Patisseries');
        $admin = $this->makeUser(Permissions::ADMIN);

        $this->actingAs($admin)->get(route('admin.businesses.show', $business->id))->assertOk()->assertSee(__('category_review.panel.not_checked'));

        $this->actingAs($admin)->postJson(route('admin.category-review.scan'), ['scope' => 'unchecked', 'cursor' => 0, 'ai' => false])
            ->assertOk()->assertJsonPath('processed', 1)->assertJsonPath('suggested', 1)->assertJsonPath('done', true)->assertJsonPath('remaining', 0);
        $this->actingAs($admin)->postJson(route('admin.category-review.scan'), ['scope' => 'everything'])->assertUnprocessable();

        $this->actingAs($admin)->get(route('admin.businesses.show', $business->id))->assertOk()->assertSee($this->category('bakeries-sweets')->name_ar);
        $this->actingAs($admin)->post(route('admin.category-review.check', $business->id))->assertRedirect()->assertSessionHas('info');
    }

    public function test_only_editors_reach_smart_categories(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get(route('admin.category-review.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.category-review.scan'), ['scope' => 'unchecked'])->assertForbidden();
        $this->actingAs($user)->postJson(route('admin.category-review.apply-confident'))->assertForbidden();
    }

    public function test_imports_use_the_classifier(): void
    {
        $matcher = app(CategoryMatcher::class);

        $ids = $matcher->match(['Travel and Tourism', 'Coffee Shops and Cafe', 'Bakeries & Patisseries'], [], 'TabiBoba Cafe');
        $this->assertSame($this->category('cafes')->id, $ids[0]);
        $this->assertSame([$this->category('other-businesses')->id], $matcher->match(['Business'], [], 'Al Noor Est'));
    }

    private function category(string $slug): Category
    {
        return Category::query()->where('slug', $slug)->firstOrFail();
    }

    private function business(string $name, string $category, ?string $keywords): Business
    {
        return Business::factory()->published()->create([
            'name_en' => $name, 'name_ar' => '', 'keywords' => $keywords, 'category_id' => $this->category($category)->id,
            'description_en' => null, 'description_ar' => null, 'tagline_en' => null, 'tagline_ar' => null, 'services_text' => null, 'is_demo' => false,
        ]);
    }
}
