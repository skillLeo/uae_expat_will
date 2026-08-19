<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every runtime setting lives here, never in .env. The mailer, the payment
        // gateway and the WhatsApp client all rebuild their config from these rows
        // so an administrator can change them without a deploy.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // branding|contact|commercial|mail|whatsapp|payment|analytics|security|
            // retention|features
            $table->string('group', 40)->index();
            $table->string('key')->unique();
            $table->longText('value')->nullable();
            // string|text|boolean|integer|json|encrypted|file
            $table->string('type', 20)->default('string');
            $table->string('label');
            $table->text('help_text')->nullable();
            // Only a public setting may be shipped to the browser. Credentials are
            // never public, and the Inertia share filters on this column.
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('settings_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_id')->constrained()->cascadeOnDelete();
            // Encrypted values are stored here redacted, not in clear.
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings_history');
        Schema::dropIfExists('settings');
    }
};
