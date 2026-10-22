<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * First-party, cookie-free visitor analytics: one row per page view (no IP
 * address, no user agent, no personal data — the visitor is an anonymous
 * hashed token), and a running view counter per page shown to visitors.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->char('visitor', 20);
            $table->char('session', 20);
            $table->boolean('is_new')->default(false);
            $table->string('path', 300);
            $table->string('page_key', 120)->nullable();
            $table->string('title', 200)->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('channel', 12);
            $table->string('referrer', 120)->nullable();
            $table->string('utm_source', 60)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('device', 10);
            $table->string('browser', 20)->nullable();
            $table->string('os', 20)->nullable();
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('last_seen_at')->useCurrent();

            $table->index('created_at');
            $table->index('last_seen_at');
            $table->index(['page_key', 'created_at']);
            $table->index(['session', 'created_at']);
        });

        Schema::create('page_stats', function (Blueprint $table) {
            $table->string('page_key', 120)->primary();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestampTz('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_stats');
        Schema::dropIfExists('page_views');
    }
};
