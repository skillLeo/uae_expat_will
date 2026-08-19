<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('assessment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // cookie|terms|privacy|sensitive_data|declaration|draft_approval
            $table->string('type', 30)->index();
            $table->string('version', 20);
            // A hash of the exact wording shown. This is what proves WHAT was agreed,
            // not merely that something was agreed.
            $table->string('wording_hash', 64);
            $table->boolean('accepted')->default(true);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('related_reference')->nullable()->index();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });

        Schema::create('sessions_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('session_id')->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_label')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        // Extend spatie/laravel-activitylog with the request context an audit
        // trail actually needs to be evidential.
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable()->after('properties');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('route')->nullable()->after('user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', fn (Blueprint $t) => $t->dropColumn(['ip_address', 'user_agent', 'route']));
        Schema::dropIfExists('sessions_devices');
        Schema::dropIfExists('consents');
    }
};
