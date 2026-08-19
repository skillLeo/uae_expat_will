<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index();
            $table->string('channel', 20); // email|whatsapp
            $table->string('subject')->nullable();
            $table->longText('body');
            $table->string('whatsapp_header')->nullable();
            $table->string('whatsapp_footer')->nullable();
            $table->json('whatsapp_buttons')->nullable();
            $table->json('variables')->nullable();
            $table->string('meta_template_name')->nullable();
            $table->string('meta_status', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale', 10)->default('en');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['key', 'channel', 'locale']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->string('template_key')->index();
            $table->string('channel', 20);
            $table->string('recipient');
            // queued|sent|delivered|failed|read
            $table->string('status', 20)->default('queued')->index();
            $table->string('provider_reference')->nullable();
            $table->text('error')->nullable();
            // The rendered payload. A restricted case's reason NEVER appears here,
            // because it never enters the template context in the first place.
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            // Set when this email was sent because a WhatsApp message failed.
            $table->foreignId('fallback_of_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('notification_templates');
    }
};
