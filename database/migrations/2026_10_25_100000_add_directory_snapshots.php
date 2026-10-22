<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Directory snapshots: what another directory says about a listing (its
 * description text, business-card image, categories…) is kept privately on
 * business_sources. It feeds the AI rewrite and the admin's review, and is
 * never published as-is.
 *
 * Also adds the "pages" discovery mode for directories without a sitemap
 * that list their companies on numbered pages (…/listings?page=1…412).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_sources', function (Blueprint $table) {
            $table->jsonb('details')->nullable()->after('url');
        });

        DB::statement('ALTER TABLE scrape_sources DROP CONSTRAINT IF EXISTS scrape_sources_discovery_check');
        DB::statement("ALTER TABLE scrape_sources ADD CONSTRAINT scrape_sources_discovery_check CHECK (discovery IN ('sitemap','links','urls','pages'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE scrape_sources DROP CONSTRAINT IF EXISTS scrape_sources_discovery_check');
        DB::statement("ALTER TABLE scrape_sources ADD CONSTRAINT scrape_sources_discovery_check CHECK (discovery IN ('sitemap','links','urls'))");

        Schema::table('business_sources', function (Blueprint $table) {
            $table->dropColumn('details');
        });
    }
};
