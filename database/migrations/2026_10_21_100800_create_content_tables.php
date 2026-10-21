<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title_ar', 200);
            $table->string('title_en', 200);
            $table->longText('body_ar')->nullable(); // Markdown, rendered with raw HTML stripped
            $table->longText('body_en')->nullable();
            $table->string('meta_title_ar', 160)->nullable();
            $table->string('meta_title_en', 160)->nullable();
            $table->string('meta_description_ar', 300)->nullable();
            $table->string('meta_description_en', 300)->nullable();
            $table->boolean('is_published')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('audience', 20)->default('general');
            $table->string('question_ar', 300);
            $table->string('question_en', 300);
            $table->text('answer_ar');
            $table->text('answer_en');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['audience', 'is_active', 'sort_order']);
        });
        DB::statement("ALTER TABLE faqs ADD CONSTRAINT faqs_audience_check CHECK (audience IN ('general','owners','users'))");

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('placement', 30)->default('home_promo');
            $table->string('title_ar', 160);
            $table->string('title_en', 160);
            $table->string('subtitle_ar', 300)->nullable();
            $table->string('subtitle_en', 300)->nullable();
            $table->string('cta_label_ar', 60)->nullable();
            $table->string('cta_label_en', 60)->nullable();
            $table->string('link_url', 300)->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['placement', 'is_active', 'sort_order']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 40);
            $table->string('key', 80)->unique();
            $table->jsonb('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->string('phone', 32)->nullable();
            $table->string('subject', 200);
            $table->text('message');
            $table->string('locale', 5)->default('ar');
            $table->string('status', 20)->default('new');
            $table->string('ip_hash', 64)->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_status_check CHECK (status IN ('new','read','replied','archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('pages');
    }
};
