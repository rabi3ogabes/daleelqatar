<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Full moderation history of each listing
        Schema::create('business_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['business_id', 'created_at']);
        });

        Schema::create('business_claim_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('claimant_name', 120);
            $table->string('position', 120)->nullable();
            $table->string('phone', 32);
            $table->string('email', 191);
            $table->text('message')->nullable();
            $table->string('proof_disk', 40)->nullable();
            $table->string('proof_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE business_claim_requests ADD CONSTRAINT business_claim_requests_status_check CHECK (status IN ('pending','approved','rejected','cancelled'))");
        // A user may only have one open claim per business, and a business only one open claim at a time.
        DB::statement("CREATE UNIQUE INDEX business_claims_one_pending_per_user ON business_claim_requests (business_id, user_id) WHERE status = 'pending'");

        Schema::create('business_verification_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('commercial_registration', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('document_disk', 40)->nullable();
            $table->string('document_path')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
        DB::statement("ALTER TABLE business_verification_requests ADD CONSTRAINT business_verification_requests_status_check CHECK (status IN ('pending','approved','rejected','cancelled'))");
        DB::statement("CREATE UNIQUE INDEX business_verifications_one_pending ON business_verification_requests (business_id) WHERE status = 'pending'");
    }

    public function down(): void
    {
        Schema::dropIfExists('business_verification_requests');
        Schema::dropIfExists('business_claim_requests');
        Schema::dropIfExists('business_status_changes');
    }
};
