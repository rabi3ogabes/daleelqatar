<?php

use App\Support\Search\ArabicNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Trigram matching for fuzzy / partial search. On Supabase extensions live in the "extensions" schema.
        $hasExtensionsSchema = DB::selectOne("SELECT 1 AS ok FROM pg_namespace WHERE nspname = 'extensions'") !== null;
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm'.($hasExtensionsSchema ? ' WITH SCHEMA extensions' : ''));

        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained('municipalities')->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnDelete();

            $table->string('name_ar', 160);
            $table->string('name_en', 160);
            $table->string('slug', 180)->unique();
            $table->string('tagline_ar', 200)->nullable();
            $table->string('tagline_en', 200)->nullable();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('keywords', 500)->nullable();
            $table->text('services_text')->nullable(); // denormalised from business_services for search

            $table->string('address_ar', 300)->nullable();
            $table->string('address_en', 300)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('phone', 32)->nullable();
            $table->string('whatsapp', 32)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('website', 300)->nullable();

            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();

            // Moderation workflow
            $table->string('status', 20)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->text('moderation_notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();

            // Development sample data is always flagged and labelled
            $table->boolean('is_demo')->default(false);

            // Aggregates maintained by services (never edited by hand)
            $table->decimal('rating_avg', 3, 2)->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);

            $table->string('meta_title_ar', 160)->nullable();
            $table->string('meta_title_en', 160)->nullable();
            $table->string('meta_description_ar', 300)->nullable();
            $table->string('meta_description_en', 300)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
            $table->index(['status', 'category_id']);
            $table->index(['status', 'municipality_id', 'area_id']);
            $table->index(['status', 'is_featured']);
            $table->index(['status', 'is_verified']);
            $table->index(['status', 'rating_avg']);
            $table->index('owner_id');
        });

        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_status_check CHECK (status IN ('draft','pending','approved','rejected','suspended','archived'))");
        DB::statement('ALTER TABLE businesses ADD CONSTRAINT businesses_rating_check CHECK (rating_avg IS NULL OR rating_avg BETWEEN 1 AND 5)');
        DB::statement('ALTER TABLE businesses ADD CONSTRAINT businesses_lat_check CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE businesses ADD CONSTRAINT businesses_lng_check CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)');

        // Search document: Arabic-normalised (alef/yaa/taa-marbuta forms, tashkeel removed), lower-cased.
        $n = fn (string $expr) => ArabicNormalizer::sql($expr);
        DB::statement("ALTER TABLE businesses ADD COLUMN search_document text GENERATED ALWAYS AS (".
            $n("coalesce(name_ar,'') || ' ' || coalesce(name_en,'') || ' ' || coalesce(keywords,'') || ' ' || coalesce(services_text,'')").
            ') STORED');
        DB::statement("ALTER TABLE businesses ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (".
            "setweight(to_tsvector('simple'::regconfig, ".$n("coalesce(name_ar,'') || ' ' || coalesce(name_en,'')")."), 'A') || ".
            "setweight(to_tsvector('simple'::regconfig, ".$n("coalesce(keywords,'') || ' ' || coalesce(services_text,'') || ' ' || coalesce(tagline_ar,'') || ' ' || coalesce(tagline_en,'')")."), 'B') || ".
            "setweight(to_tsvector('simple'::regconfig, ".$n("coalesce(description_ar,'') || ' ' || coalesce(description_en,'')")."), 'C')".
            ') STORED');
        DB::statement('CREATE INDEX businesses_search_vector_idx ON businesses USING gin (search_vector)');
        DB::statement('CREATE INDEX businesses_search_document_trgm_idx ON businesses USING gin (search_document gin_trgm_ops)');

        // Secondary categories (the primary category lives on businesses.category_id)
        Schema::create('business_category', function (Blueprint $table) {
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->primary(['business_id', 'category_id']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_category');
        Schema::dropIfExists('businesses');
    }
};
