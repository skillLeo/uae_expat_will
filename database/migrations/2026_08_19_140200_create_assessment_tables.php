<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // Resume without an account: the token is the only credential, so it is
            // random and unguessable, and it expires.
            $table->string('session_token', 80)->unique();
            // An in-flight assessment stays pinned to the version it started on.
            // Publishing a new version never changes an assessment already running.
            $table->foreignId('questionnaire_version_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('in_progress'); // in_progress|completed|abandoned
            $table->string('outcome', 30)->nullable()->index();
            $table->text('outcome_detail')->nullable();
            // Itemised per question, so the case detail can show WHY, rule by rule.
            $table->json('trigger_reasons')->nullable();
            $table->json('flags')->nullable();
            $table->json('reminders')->nullable();
            $table->json('route_marks')->nullable();
            $table->string('current_question_key', 40)->nullable();
            $table->string('abandoned_at_question_key', 40)->nullable();

            $table->string('source')->nullable()->index();
            $table->string('campaign')->nullable()->index();
            $table->json('utm')->nullable();
            $table->text('referrer')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
        });

        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            // Denormalised so an answer stays readable if the version is archived.
            $table->string('question_key', 40)->index();
            // Encrypted at rest when the question is flagged sensitive. Cast is
            // applied conditionally in the model, not here.
            $table->text('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->unique(['assessment_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
        Schema::dropIfExists('assessments');
    }
};
