<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('gateway', 40);
            $table->string('gateway_reference')->nullable()->index();
            $table->text('link_url')->nullable();
            $table->string('link_token', 80)->nullable()->unique();
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('AED');
            $table->string('stage_label')->nullable();
            // pending|paid|failed|cancelled|partially_paid|refunded
            $table->string('status', 20)->default('pending')->index();
            $table->string('method', 40)->nullable(); // card|bank_transfer|cash
            $table->timestamp('paid_at')->nullable();
            $table->text('failed_reason')->nullable();
            // Raw gateway payload. NEVER contains card data — the gateway hosts
            // checkout and no PAN, CVV or expiry ever reaches this application.
            $table->json('raw_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('source', 20); // webhook|manual_check|manual_record
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('band', 1); // a|b|c|d
            $table->decimal('amount', 12, 2);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->text('deduction_reason')->nullable();
            // The full working, kept so a refund can be justified months later.
            $table->json('calculation')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seeded so band C can compute the unused portion of a fee. Editable in
        // admin, because the split between stages is a commercial decision.
        Schema::create('fee_allocations', function (Blueprint $table) {
            $table->id();
            $table->string('service_type', 40);
            $table->string('stage', 40);
            $table->decimal('percentage', 5, 2);
            $table->timestamps();

            $table->unique(['service_type', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_allocations');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
