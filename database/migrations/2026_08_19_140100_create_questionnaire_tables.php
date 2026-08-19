<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('type', 20)->default('screening'); // screening|detailed
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('questionnaire_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft'); // draft|published|archived
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['questionnaire_id', 'version_number']);
            $table->index(['questionnaire_id', 'status']);
        });

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->unsignedInteger('order')->default(0);
            // single_select|multi_select|country_select|text|textarea|boolean|number|date
            $table->string('type', 20);
            $table->text('prompt');
            $table->text('help_text')->nullable();
            $table->text('privacy_note')->nullable();
            $table->text('security_note')->nullable();
            $table->boolean('is_required')->default(true);
            // A sensitive answer is encrypted at rest and excluded from exports and
            // analytics. Religion, capacity and family detail are all sensitive.
            $table->boolean('is_sensitive')->default(false);
            $table->string('section_key', 40)->nullable()->index();
            $table->string('placeholder')->nullable();
            $table->integer('min')->nullable();
            $table->integer('max')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['questionnaire_version_id', 'key']);
            $table->index(['questionnaire_version_id', 'order']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->unsignedInteger('order')->default(0);
            $table->text('label');
            $table->text('description')->nullable();
            // "None of these" — selecting it clears every other option, and selecting
            // any other option clears it. Enforced in Vue AND revalidated server-side.
            $table->boolean('is_exclusive')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['question_id', 'key']);
        });

        Schema::create('question_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_question_id')->constrained('questions')->cascadeOnDelete();
            // equals|not_equals|in|not_in|answered|not_answered
            $table->string('operator', 20);
            $table->json('value')->nullable();
            $table->string('action', 20)->default('show'); // show|hide|skip_section
            $table->string('target_section_key', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('routing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('priority')->default(100);
            // continue|continue_flag|continue_reminder|continue_route_mark|review|
            // urgent_review|stop_refer|stop_ineligible
            $table->string('outcome', 30);
            $table->text('outcome_detail')->nullable();
            $table->string('flag_key', 60)->nullable();
            $table->string('reminder_key', 60)->nullable();
            $table->string('route_mark_key', 60)->nullable();
            $table->boolean('is_terminal')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['questionnaire_version_id', 'priority']);
        });

        Schema::create('routing_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->string('operator', 20);
            $table->json('value')->nullable();
            // Conditions sharing a group_index AND together; groups OR together.
            // This is what makes the religion-dependent rules (R-11) expressible
            // without code: one group holds the distribution answer AND Q5 = Muslim.
            $table->unsignedInteger('group_index')->default(0);
            $table->string('group_operator', 3)->default('and');
            $table->timestamps();

            $table->index(['routing_rule_id', 'group_index']);
        });

        Schema::create('questionnaire_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->text('text');
            $table->boolean('is_required')->default(true);
            $table->timestamps();
        });

        Schema::create('questionnaire_result_screens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_version_id')->constrained()->cascadeOnDelete();
            $table->string('outcome', 30);
            $table->text('heading');
            $table->text('body');
            $table->string('primary_action_label')->nullable();
            $table->string('secondary_action_label')->nullable();
            $table->json('extra')->nullable();
            $table->timestamps();

            // Explicit name: the auto-generated one is 68 characters, and MySQL
            // caps identifiers at 64. It passes silently on SQLite and fails on
            // the production database, which is the worst place to find out.
            $table->unique(['questionnaire_version_id', 'outcome'], 'qrs_version_outcome_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_result_screens');
        Schema::dropIfExists('questionnaire_declarations');
        Schema::dropIfExists('routing_rule_conditions');
        Schema::dropIfExists('routing_rules');
        Schema::dropIfExists('question_conditions');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('questionnaire_versions');
        Schema::dropIfExists('questionnaires');
    }
};
