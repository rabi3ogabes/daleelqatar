<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->string('slug', 180)->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->string('discount_label_ar', 40)->nullable();
            $table->string('discount_label_en', 40)->nullable();
            $table->text('terms_ar')->nullable();
            $table->text('terms_en')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('status', 20)->default('pending');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->text('rejection_reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_active', 'starts_at', 'ends_at']);
            $table->index(['business_id', 'status']);
        });
        DB::statement("ALTER TABLE offers ADD CONSTRAINT offers_status_check CHECK (status IN ('draft','pending','approved','rejected'))");
        DB::statement('ALTER TABLE offers ADD CONSTRAINT offers_dates_check CHECK (ends_at > starts_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
