<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smart categories: every listing is checked against the taxonomy and the
 * proposed category waits in category_reviews until an administrator
 * applies it (or keeps the current one). Also adds the categories that
 * imported listings needed but the taxonomy did not have yet.
 */
return new class extends Migration
{
    /** parent slug => [slug, name_ar, name_en] */
    private const NEW_CATEGORIES = [
        'shopping-retail' => [['shopping-malls', 'مراكز تسوق', 'Shopping Malls']],
        'health-wellness' => [['opticians', 'نظارات وبصريات', 'Opticians']],
        'automotive' => [['petrol-stations', 'محطات وقود', 'Petrol Stations']],
        'professional-services' => [['media-publishing', 'إعلام ونشر', 'Media & Publishing']],
        'hotels-tourism' => [['parks-attractions', 'حدائق ومعالم سياحية', 'Parks & Attractions']],
        'logistics-transport' => [['airlines-aviation', 'طيران وخطوط جوية', 'Airlines & Aviation']],
        'government-community' => [
            ['government-offices', 'جهات حكومية', 'Government Offices'],
            ['embassies-consulates', 'سفارات وقنصليات', 'Embassies & Consulates'],
            ['non-profits', 'جمعيات ومؤسسات خيرية', 'Non-profits & Associations'],
        ],
    ];

    public function up(): void
    {
        Schema::create('category_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('current_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('suggested_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->jsonb('secondary_ids')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('method', 10)->default('rules');
            $table->string('kind', 10)->default('fix');
            $table->string('status', 12)->default('pending');
            $table->jsonb('reasons')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'confidence']);
            $table->index(['current_category_id', 'suggested_category_id']);
        });
        DB::statement("ALTER TABLE category_reviews ADD CONSTRAINT category_reviews_status_check CHECK (status IN ('pending','correct','applied','kept'))");
        DB::statement("ALTER TABLE category_reviews ADD CONSTRAINT category_reviews_method_check CHECK (method IN ('rules','ai'))");
        DB::statement("ALTER TABLE category_reviews ADD CONSTRAINT category_reviews_kind_check CHECK (kind IN ('fix','refine','same'))");

        $this->addCategories();
    }

    public function down(): void
    {
        Schema::dropIfExists('category_reviews');
    }

    /** Only adds what is missing, so names or order edited by admins are never overwritten. */
    private function addCategories(): void
    {
        if (! DB::table('categories')->exists()) {
            return; // A fresh install: the CategorySeeder creates the full taxonomy.
        }
        $now = now();
        if (! DB::table('categories')->where('slug', 'government-community')->exists()) {
            DB::table('categories')->insert([
                'slug' => 'government-community', 'parent_id' => null, 'name_ar' => 'جهات حكومية ومجتمعية', 'name_en' => 'Government & Community',
                'description_ar' => 'الوزارات والجهات الحكومية والسفارات والقنصليات والجمعيات في قطر.',
                'description_en' => 'Ministries, government offices, embassies, consulates and associations in Qatar.',
                'icon' => 'building', 'sort_order' => (int) DB::table('categories')->whereNull('parent_id')->max('sort_order') + 1,
                'is_active' => true, 'is_featured' => false, 'created_at' => $now, 'updated_at' => $now,
            ]);
            // "Other businesses" stays last.
            DB::table('categories')->where('slug', 'other-businesses')->update(['sort_order' => (int) DB::table('categories')->whereNull('parent_id')->max('sort_order') + 1]);
        }

        foreach (self::NEW_CATEGORIES as $parentSlug => $children) {
            $parent = DB::table('categories')->where('slug', $parentSlug)->first(['id', 'icon']);
            if ($parent === null) {
                continue;
            }
            foreach ($children as [$slug, $ar, $en]) {
                if (DB::table('categories')->where('slug', $slug)->exists()) {
                    continue;
                }
                DB::table('categories')->insert([
                    'slug' => $slug, 'parent_id' => $parent->id, 'name_ar' => $ar, 'name_en' => $en, 'icon' => $parent->icon,
                    'sort_order' => (int) DB::table('categories')->where('parent_id', $parent->id)->max('sort_order') + 1,
                    'is_active' => true, 'is_featured' => false, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }
};
