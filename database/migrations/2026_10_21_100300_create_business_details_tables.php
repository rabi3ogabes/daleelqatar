<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('disk', 40);
            $table->string('path');
            $table->string('caption_ar', 200)->nullable();
            $table->string('caption_en', 200)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'sort_order']);
        });

        Schema::create('business_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->string('description_ar', 500)->nullable();
            $table->string('description_en', 500)->nullable();
            $table->decimal('price_from', 10, 2)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'sort_order']);
        });

        // Times are local to Qatar (Asia/Qatar, UTC+3, no DST). closes_at < opens_at means the
        // period runs past midnight; 00:00–24:00 means open 24 hours.
        Schema::create('opening_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Sunday … 6 = Saturday
            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['business_id', 'day_of_week', 'opens_at']);
            $table->index(['day_of_week', 'opens_at', 'closes_at']);
        });
        DB::statement('ALTER TABLE opening_hours ADD CONSTRAINT opening_hours_day_check CHECK (day_of_week BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE opening_hours ADD CONSTRAINT opening_hours_times_check CHECK (is_closed OR (opens_at IS NOT NULL AND closes_at IS NOT NULL))');

        Schema::create('business_social_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('url', 300);
            $table->timestamps();

            $table->unique(['business_id', 'platform']);
        });
        DB::statement("ALTER TABLE business_social_links ADD CONSTRAINT business_social_links_platform_check CHECK (platform IN ('instagram','x','facebook','tiktok','snapchat','linkedin','youtube'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_social_links');
        Schema::dropIfExists('opening_hours');
        Schema::dropIfExists('business_services');
        Schema::dropIfExists('business_images');
    }
};
