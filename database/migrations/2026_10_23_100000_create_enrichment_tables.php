<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Smart enrichment: listings are matched to Google Maps places and the
 * company website, and every proposed change waits in business_suggestions
 * until an administrator approves or edits it.
 *
 * Only the Google Place ID (and the Maps link built from it) is stored for
 * Google; ratings and reviews are fetched live and cached briefly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('google_place_id', 255)->nullable()->unique()->after('longitude');
            $table->string('google_maps_url', 500)->nullable()->after('google_place_id');
            $table->string('website_status', 20)->nullable()->after('website');
            $table->timestamp('enriched_at')->nullable()->after('imported_at');
        });
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_website_status_check CHECK (website_status IS NULL OR website_status IN ('found','inferred','none','unreachable'))");

        Schema::create('business_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('match_confidence')->nullable();
            $table->jsonb('match')->nullable();
            $table->jsonb('fields');
            $table->jsonb('sources')->nullable();
            $table->jsonb('notes')->nullable();
            $table->jsonb('applied_fields')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['business_id', 'status']);
        });
        DB::statement("ALTER TABLE business_suggestions ADD CONSTRAINT business_suggestions_status_check CHECK (status IN ('pending','applied','partial','rejected','superseded','empty'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_suggestions');
        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_website_status_check');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropUnique(['google_place_id']);
            $table->dropColumn(['google_place_id', 'google_maps_url', 'website_status', 'enriched_at']);
        });
    }
};
