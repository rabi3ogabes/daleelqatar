<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk import (spreadsheets) and automated discovery (directory crawler).
 *
 * - business_identifiers: normalised phones, e-mails and website domains used
 *   to detect duplicates across files and sources (one owner per identifier).
 * - business_sources: where each listing came from, so re-importing the same
 *   file or crawling the same page never creates a second copy.
 * - import_batches / import_rows: an uploaded file is staged row by row and
 *   processed in small chunks, so large files fit serverless time limits.
 * - scrape_sources / scrape_urls: crawler configuration and its URL queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('name_key', 200)->nullable()->after('slug');
            $table->text('opening_hours_text')->nullable()->after('services_text');
            $table->jsonb('extra_phones')->nullable()->after('whatsapp');
            $table->jsonb('extra_emails')->nullable()->after('email');
            $table->string('geo_precision', 20)->nullable()->after('longitude');
            $table->timestamp('imported_at')->nullable()->after('is_demo');
            $table->index('name_key');
        });
        DB::statement("ALTER TABLE businesses ADD CONSTRAINT businesses_geo_precision_check CHECK (geo_precision IS NULL OR geo_precision IN ('exact','approximate'))");

        Schema::create('business_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);
            $table->string('value', 191);
            $table->unique(['type', 'value']);
            $table->index('business_id');
        });
        DB::statement("ALTER TABLE business_identifiers ADD CONSTRAINT business_identifiers_type_check CHECK (type IN ('phone','email','domain'))");

        Schema::create('business_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('source', 80);
            $table->string('external_id', 191);
            $table->string('url', 500)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->unique(['source', 'external_id']);
            $table->index('business_id');
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_name', 80);
            $table->string('original_name', 255);
            $table->string('disk', 40);
            $table->string('path', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('status', 20)->default('uploaded');
            $table->string('sheet', 120)->nullable();
            $table->unsignedInteger('header_row')->nullable();
            $table->jsonb('columns')->nullable();
            $table->jsonb('mapping')->nullable();
            $table->jsonb('options')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('merged_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE import_batches ADD CONSTRAINT import_batches_status_check CHECK (status IN ('uploaded','ready','staging','processing','completed','failed','cancelled'))");

        Schema::create('import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('payload');
            $table->string('status', 12)->default('pending');
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message', 255)->nullable();
            $table->index(['import_batch_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE import_rows ADD CONSTRAINT import_rows_status_check CHECK (status IN ('pending','created','merged','skipped','failed'))");

        Schema::create('scrape_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 80)->unique();
            $table->string('base_url', 300);
            $table->string('discovery', 20)->default('sitemap');
            $table->text('start_urls');
            $table->string('url_pattern', 500)->nullable();
            $table->jsonb('selectors')->nullable();
            $table->jsonb('settings')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_discovered_at')->nullable();
            $table->string('last_status', 500)->nullable();
            $table->jsonb('stats')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE scrape_sources ADD CONSTRAINT scrape_sources_discovery_check CHECK (discovery IN ('sitemap','links','urls'))");

        Schema::create('scrape_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scrape_source_id')->constrained()->cascadeOnDelete();
            $table->string('url', 2000);
            $table->char('url_hash', 40);
            $table->string('status', 12)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message', 255)->nullable();
            $table->timestamps();
            $table->unique(['scrape_source_id', 'url_hash']);
            $table->index(['scrape_source_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE scrape_urls ADD CONSTRAINT scrape_urls_status_check CHECK (status IN ('pending','done','failed','skipped'))");

        // Contact details are revealed on demand; record those taps like other interactions.
        DB::statement('ALTER TABLE business_events DROP CONSTRAINT business_events_type_check');
        DB::statement("ALTER TABLE business_events ADD CONSTRAINT business_events_type_check CHECK (type IN ('view','call','whatsapp','website','directions','share','offer_view','phone_reveal','email_reveal'))");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM business_events WHERE type IN ('phone_reveal','email_reveal')");
        DB::statement('ALTER TABLE business_events DROP CONSTRAINT business_events_type_check');
        DB::statement("ALTER TABLE business_events ADD CONSTRAINT business_events_type_check CHECK (type IN ('view','call','whatsapp','website','directions','share','offer_view'))");

        Schema::dropIfExists('scrape_urls');
        Schema::dropIfExists('scrape_sources');
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('business_sources');
        Schema::dropIfExists('business_identifiers');

        DB::statement('ALTER TABLE businesses DROP CONSTRAINT IF EXISTS businesses_geo_precision_check');
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['name_key']);
            $table->dropColumn(['name_key', 'opening_hours_text', 'extra_phones', 'extra_emails', 'geo_precision', 'imported_at']);
        });
    }
};
