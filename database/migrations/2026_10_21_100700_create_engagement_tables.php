<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'business_id']);
            $table->index('business_id');
        });

        // Search analytics — queries are stored without personal data (IP addresses are hashed).
        Schema::create('search_events', function (Blueprint $table) {
            $table->id();
            $table->string('query', 200)->nullable();
            $table->string('normalized_query', 200)->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('municipality_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('results_count')->default(0);
            $table->string('locale', 5)->nullable();
            $table->string('source', 10)->default('web');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index(['normalized_query', 'created_at']);
        });

        // Listing engagement: profile views and contact interactions, plus offer views.
        Schema::create('business_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('source', 10)->default('web');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'type', 'created_at']);
            $table->index(['type', 'created_at']);
        });
        DB::statement("ALTER TABLE business_events ADD CONSTRAINT business_events_type_check CHECK (type IN ('view','call','whatsapp','website','directions','share','offer_view'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_events');
        Schema::dropIfExists('search_events');
        Schema::dropIfExists('favorites');
    }
};
