<?php

namespace Tests\Feature\Admin;

use App\Enums\BusinessStatus;
use App\Models\Area;
use App\Models\Category;
use App\Models\Municipality;

class TaxonomyTest extends AdminTestCase
{
    public function test_category_in_use_cannot_be_deleted(): void
    {
        $business = $this->completeBusiness(BusinessStatus::Approved);
        $category = $business->category;

        $this->actingAs($this->admin())->delete(route('ar.admin.categories.destroy', $category))->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unused_category_can_be_created_and_deleted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('ar.admin.categories.store'), [
            'name_ar' => 'قسم تجريبي', 'name_en' => 'Pet Care', 'icon' => 'heart', 'is_active' => 1,
        ])->assertRedirect(route('ar.admin.categories.index'));

        $category = Category::query()->where('slug', 'pet-care')->firstOrFail();
        $this->assertTrue($category->is_active);
        $this->assertNull($category->parent_id);

        $this->actingAs($admin)->delete(route('ar.admin.categories.destroy', $category))->assertSessionHas('success');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_categories_can_be_reordered(): void
    {
        $roots = Category::query()->roots()->ordered()->get();
        [$first, $second] = [$roots[0], $roots[1]];

        $this->actingAs($this->admin())->post(route('ar.admin.categories.move', [$second, 'up']))->assertRedirect();

        $this->assertSame($second->id, Category::query()->roots()->ordered()->first()->id);
        $this->assertSame($first->id, Category::query()->roots()->ordered()->skip(1)->first()->id);
    }

    public function test_municipality_with_areas_cannot_be_deleted_and_areas_belong_to_their_municipality(): void
    {
        $admin = $this->admin();
        $doha = Municipality::query()->where('slug', 'doha')->firstOrFail();

        $this->actingAs($admin)->delete(route('ar.admin.municipalities.destroy', $doha))->assertSessionHas('error');

        $this->actingAs($admin)->post(route('ar.admin.areas.store', $doha), [
            'municipality_id' => $doha->id, 'name_ar' => 'منطقة جديدة', 'name_en' => 'New District', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertSame($doha->id, Area::query()->where('slug', 'new-district')->value('municipality_id'));
    }
}
