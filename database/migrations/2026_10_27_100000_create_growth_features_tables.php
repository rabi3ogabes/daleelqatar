<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

/**
 * Quote requests (one request → several matching businesses), owner insights
 * (search appearances, QR scans, monthly report log), Ramadan & holiday
 * hours, curated guides, and the two staff permissions that go with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('origin_business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->string('title', 140);
            $table->text('details');
            $table->string('timing', 12);
            $table->string('contact_method', 10);
            $table->string('contact_phone', 30)->nullable();
            $table->string('locale', 5)->default('ar');
            $table->string('status', 10)->default('open');
            $table->unsignedSmallInteger('recipients_count')->default(0);
            $table->unsignedSmallInteger('replies_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 20)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
        DB::statement("ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_status_check CHECK (status IN ('open','closed','expired','removed'))");
        DB::statement("ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_timing_check CHECK (timing IN ('asap','this_week','this_month','flexible'))");
        DB::statement("ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_contact_check CHECK (contact_method IN ('phone','whatsapp','email'))");

        Schema::create('quote_request_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('sent');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->text('reply')->nullable();
            $table->string('price_note', 120)->nullable();
            $table->string('declined_reason', 20)->nullable();
            $table->timestamps();

            $table->unique(['quote_request_id', 'business_id']);
            $table->index(['business_id', 'status', 'created_at']);
        });
        DB::statement("ALTER TABLE quote_request_recipients ADD CONSTRAINT quote_recipients_status_check CHECK (status IN ('sent','viewed','replied','declined'))");

        // Per-business daily counters that are not individual events: search appearances and poster scans.
        Schema::create('business_daily_stats', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('qr_scans')->default(0);

            $table->primary(['business_id', 'day']);
            $table->index('day');
        });

        // One row per owner and month once the monthly insights e-mail has gone out (keeps the send idempotent).
        Schema::create('insight_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7);
            $table->timestamp('sent_at')->useCurrent();

            $table->unique(['user_id', 'period']);
        });

        Schema::create('seasonal_periods', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('kind', 16)->default('other');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_active')->default(true);
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_on', 'ends_on']);
        });
        DB::statement('ALTER TABLE seasonal_periods ADD CONSTRAINT seasonal_periods_dates_check CHECK (ends_on >= starts_on)');
        DB::statement("ALTER TABLE seasonal_periods ADD CONSTRAINT seasonal_periods_kind_check CHECK (kind IN ('ramadan','eid','national_day','other'))");

        Schema::create('special_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seasonal_period_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'seasonal_period_id', 'day_of_week', 'opens_at']);
            $table->index(['seasonal_period_id', 'day_of_week']);
        });
        DB::statement('ALTER TABLE special_hours ADD CONSTRAINT special_hours_day_check CHECK (day_of_week BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE special_hours ADD CONSTRAINT special_hours_times_check CHECK (is_closed OR (opens_at IS NOT NULL AND closes_at IS NOT NULL))');

        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->text('intro_ar')->nullable();
            $table->text('intro_en')->nullable();
            $table->string('meta_description_ar', 300)->nullable();
            $table->string('meta_description_en', 300)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('open_24h')->default(false);
            $table->boolean('verified_only')->default(false);
            $table->unsignedSmallInteger('max_items')->default(12);
            $table->string('status', 10)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
        DB::statement("ALTER TABLE guides ADD CONSTRAINT guides_status_check CHECK (status IN ('draft','published'))");

        // Businesses an editor pinned to the top of a guide, in their order.
        Schema::create('guide_business', function (Blueprint $table) {
            $table->foreignId('guide_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['guide_id', 'business_id']);
        });

        // Qatar National Day is a fixed date. Ramadan dates depend on the moon sighting,
        // so that period starts inactive and an admin confirms the dates when announced.
        $now = now();
        DB::table('seasonal_periods')->insert([
            ['slug' => 'national-day-2026', 'name_ar' => 'اليوم الوطني 2026', 'name_en' => 'National Day 2026', 'kind' => 'national_day',
                'starts_on' => '2026-12-18', 'ends_on' => '2026-12-18', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ramadan-2027', 'name_ar' => 'رمضان 2027', 'name_en' => 'Ramadan 2027', 'kind' => 'ramadan',
                'starts_on' => '2027-02-08', 'ends_on' => '2027-03-09', 'is_active' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->grant(['quotes.manage', 'guides.manage']);
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', ['quotes.manage', 'guides.manage'])->delete();

        Schema::dropIfExists('guide_business');
        Schema::dropIfExists('guides');
        Schema::dropIfExists('special_hours');
        Schema::dropIfExists('seasonal_periods');
        Schema::dropIfExists('insight_reports');
        Schema::dropIfExists('business_daily_stats');
        Schema::dropIfExists('quote_request_recipients');
        Schema::dropIfExists('quote_requests');
    }

    /** Adds the permissions and gives them to the roles listed in Permissions::matrix(). */
    private function grant(array $names): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $now = now();
        foreach ($names as $name) {
            DB::table('permissions')->insertOrIgnore(['name' => $name, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
        }
        $permissionIds = DB::table('permissions')->whereIn('name', $names)->pluck('id', 'name');
        foreach (Permissions::matrix() as $role => $permissions) {
            $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');
            if (! $roleId) {
                continue;
            }
            foreach (array_intersect($names, $permissions) as $name) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionIds[$name], 'role_id' => $roleId]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
