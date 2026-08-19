<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('purpose', 40);
            // random_bytes(32), hashed. The raw token exists ONLY in the emailed
            // URL — it is never stored, logged or recoverable from this table.
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_used', 45)->nullable();
            $table->text('user_agent_used')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 40);
            $table->string('status', 20)->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('media_id')->nullable();
            // draft|sent|amendments_requested|approved
            $table->string('status', 30)->default('draft');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('approved_by_customer')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['case_id', 'version_number']);
        });

        Schema::create('draft_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('is_within_allowance')->default(true);
            $table->string('status', 20)->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_amendments');
        Schema::dropIfExists('drafts');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('magic_links');
    }
};
