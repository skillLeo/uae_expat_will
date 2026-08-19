<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('full_name');
            $table->string('email')->index();
            $table->string('phone', 32)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('country_of_residence', 100)->nullable();
            $table->string('emirate', 60)->nullable();
            $table->string('preferred_contact_method', 20)->nullable();
            $table->string('language_support', 40)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // SLC-YYYY-NNNNN
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pathway', 40)->nullable();
            // `status` is the CUSTOMER-FACING group (8 of them).
            // `internal_status` is the internal one (27 of them) and must NEVER be
            // rendered to a customer or returned by a customer-facing endpoint.
            $table->string('status', 40)->index();
            $table->string('internal_status', 60)->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('countdown_due_at')->nullable()->index();

            // A restricted case is one flagged for capacity or undue influence.
            // The reason is encrypted and is excluded from every list, export,
            // notification body and search result. Enforced by a global scope.
            $table->boolean('is_restricted')->default(false)->index();
            $table->text('restricted_reason_encrypted')->nullable();
            $table->json('restricted_visible_to')->nullable();

            $table->string('service_type', 40)->nullable();
            $table->decimal('quoted_amount', 12, 2)->nullable();
            $table->string('currency', 3)->default('AED');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->unsignedInteger('notes_count')->default(0);
            $table->timestamp('last_contact_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        Schema::create('case_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('from_status', 60)->nullable();
            $table->string('to_status', 60);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();
        });

        Schema::create('case_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('case_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);   // email|whatsapp|phone|meeting
            $table->string('direction', 10); // inbound|outbound
            $table->text('summary')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        // THE REFUND ENGINE DEPENDS ON THIS TABLE. Nothing else can compute a
        // refund band — the band is decided by which stages had occurred when the
        // refund was requested, so these timestamps are the evidence.
        Schema::create('case_stage_timestamps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            // payment|substantive_work_started|first_draft_delivered|
            // final_approval|third_party_committed|authority_submitted
            $table->string('stage', 40);
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['case_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_stage_timestamps');
        Schema::dropIfExists('case_contacts');
        Schema::dropIfExists('case_notes');
        Schema::dropIfExists('case_status_history');
        Schema::dropIfExists('cases');
        Schema::dropIfExists('customers');
    }
};
