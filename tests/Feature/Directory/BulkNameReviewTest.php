<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\BusinessSuggestion;
use App\Support\Permissions;
use Tests\TestCase;

class BulkNameReviewTest extends TestCase
{
    private function suggestion(string $en, string $ar, array $extra = [], string $source = 'ai'): BusinessSuggestion
    {
        $business = Business::factory()->published()->create(['name_en' => $en, 'name_ar' => $en, 'is_demo' => false]);

        return BusinessSuggestion::query()->create([
            'business_id' => $business->id,
            'status' => 'pending',
            'fields' => ['name_ar' => ['value' => $ar, 'current' => $en, 'source' => $source, 'confidence' => 'medium']] + $extra,
        ]);
    }

    private function names(array $payload)
    {
        return $this->actingAs($this->makeUser(Permissions::ADMIN))->postJson('/en/admin/enrichment/names', $payload);
    }

    public function test_the_names_tab_lists_suggestions_with_filters(): void
    {
        $this->suggestion('Blue Sky Trading', 'بلو سكاي للتجارة');
        $this->suggestion('Pearl Bakery', 'مخبز اللؤلؤة', [], 'google');
        $admin = $this->makeUser(Permissions::ADMIN);

        $this->actingAs($admin)->get('/en/admin/enrichment?tab=names')->assertOk()
            ->assertSee('Blue Sky Trading')->assertSee('بلو سكاي للتجارة', false)->assertSee('Pearl Bakery');
        $this->actingAs($admin)->get('/en/admin/enrichment?tab=names&q=pearl')->assertOk()->assertSee('Pearl Bakery')->assertDontSee('Blue Sky Trading');
        $this->actingAs($admin)->get('/en/admin/enrichment?tab=names&source=google')->assertOk()->assertSee('Pearl Bakery')->assertDontSee('Blue Sky Trading');
        $this->actingAs($admin)->get('/en/admin/enrichment?tab=pending')->assertOk()->assertSee(__('enrichment.names.review_bulk', [], 'en'));
    }

    public function test_approving_selected_rows_publishes_the_names_with_edits(): void
    {
        $a = $this->suggestion('Blue Sky Trading', 'بلو سكاي للتجارة');
        $b = $this->suggestion('Pearl Bakery', 'مخبز اللؤلؤ');
        $c = $this->suggestion('Untouched Co', 'شركة لم تمس');

        $this->names(['action' => 'approve', 'mode' => 'ids', 'ids' => [$a->id, $b->id], 'edits' => [$b->id => 'مخبز اللؤلؤة']])
            ->assertOk()->assertJson(['processed' => 2, 'done' => 2, 'finished' => true, 'pending' => 1]);

        $this->assertSame('بلو سكاي للتجارة', $a->business->fresh()->name_ar);
        $this->assertSame('مخبز اللؤلؤة', $b->business->fresh()->name_ar);
        $this->assertSame('applied', $a->fresh()->status);
        $this->assertSame('pending', $c->fresh()->status);
    }

    public function test_select_all_matching_respects_filters_and_unticked_rows(): void
    {
        $keep = $this->suggestion('Al Noor Pharmacy', 'صيدلية النور');
        $skip = $this->suggestion('Al Amal Pharmacy', 'صيدلية الأمل');
        $other = $this->suggestion('Desert Cars', 'سيارات الصحراء');

        $this->names(['action' => 'reject', 'mode' => 'all', 'except' => [$skip->id], 'q' => 'pharmacy'])
            ->assertOk()->assertJson(['done' => 1, 'finished' => true]);

        $this->assertSame('rejected', $keep->fresh()->status);
        $this->assertSame('pending', $skip->fresh()->status);
        $this->assertSame('pending', $other->fresh()->status);
        $this->assertSame('Al Noor Pharmacy', $keep->business->fresh()->name_ar, 'a rejected name changes nothing');
    }

    public function test_invalid_edits_are_reported_per_row_and_the_rest_go_through(): void
    {
        $good = $this->suggestion('Good Name Co', 'شركة الاسم الجيد');
        $bad = $this->suggestion('Bad Edit Co', 'شركة التعديل');

        $response = $this->names(['action' => 'approve', 'mode' => 'ids', 'ids' => [$good->id, $bad->id], 'edits' => [$bad->id => 'English only']])->assertOk();

        $response->assertJson(['done' => 1]);
        $this->assertSame($bad->id, $response->json('failed.0.id'));
        $this->assertSame('pending', $bad->fresh()->status);
        $this->assertSame('applied', $good->fresh()->status);
    }

    public function test_only_the_name_is_taken_from_a_suggestion_with_other_fields(): void
    {
        $mixed = $this->suggestion('Mixed Co', 'شركة مختلطة', ['phone' => ['value' => '+974 4444 5555', 'current' => null, 'source' => 'google', 'confidence' => 'high']]);

        $this->names(['action' => 'approve', 'mode' => 'ids', 'ids' => [$mixed->id]])->assertOk()->assertJson(['done' => 1]);

        $fresh = $mixed->fresh();
        $this->assertSame('شركة مختلطة', $mixed->business->fresh()->name_ar);
        $this->assertSame('pending', $fresh->status, 'the phone still waits for a normal review');
        $this->assertSame(['phone'], array_keys($fresh->fields));
        $this->assertNull($mixed->business->fresh()->phone === '+974 4444 5555' ? 'applied' : null);
    }

    public function test_reviewed_rows_are_skipped_and_access_is_limited(): void
    {
        $s = $this->suggestion('Twice Co', 'شركة مرتين');
        $this->names(['action' => 'approve', 'mode' => 'ids', 'ids' => [$s->id]])->assertJson(['done' => 1]);
        $this->names(['action' => 'reject', 'mode' => 'ids', 'ids' => [$s->id]])->assertOk()->assertJson(['done' => 0]);
        $this->assertSame('applied', $s->fresh()->status);

        $this->names(['action' => 'delete', 'mode' => 'ids', 'ids' => [$s->id]])->assertStatus(422);
        $this->actingAs($this->makeUser(Permissions::MODERATOR))->postJson('/en/admin/enrichment/names', ['action' => 'approve', 'mode' => 'all'])->assertForbidden();
    }
}
