<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipalities', function (Blueprint $table) {
            $table->id();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('slug', 140)->unique();
            $table->text('description_ar')->nullable();
            $table->text('description_en')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipality_id')->constrained()->restrictOnDelete();
            $table->string('name_ar', 120);
            $table->string('name_en', 120);
            $table->string('slug', 140)->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->unique(['municipality_id', 'name_en']);
            $table->index(['municipality_id', 'is_active', 'sort_order']);
        });

        foreach (['municipalities', 'areas'] as $t) {
            DB::statement("ALTER TABLE {$t} ADD CONSTRAINT {$t}_lat_check CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)");
            DB::statement("ALTER TABLE {$t} ADD CONSTRAINT {$t}_lng_check CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('areas');
        Schema::dropIfExists('municipalities');
    }
};
