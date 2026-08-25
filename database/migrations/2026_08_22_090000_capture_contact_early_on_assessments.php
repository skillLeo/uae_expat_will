<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the lead's contact details on the assessment itself.
 *
 * They used to be collected on the final screen, which meant somebody who got
 * two thirds of the way through and stopped left nothing behind — no name, no
 * way to ask what went wrong. Summit asked for them straight after the age
 * question instead, which is the first point at which the person is known to be
 * eligible and the drop-off is worth chasing.
 *
 * Deliberately on `assessments` and not on `customers`: a customer record means
 * a matter exists. Somebody who abandons at question nine is a lead, not a
 * client, and conflating the two would put half-finished enquiries into the case
 * list and into the retention rules written for real matters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('contact_name')->nullable()->after('source');
            $table->string('contact_email')->nullable()->after('contact_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_email');
            $table->timestamp('contact_captured_at')->nullable()->after('contact_phone');

            // The follow-up list is "has contact, never completed", so it is
            // read far more often by that shape than by anything else.
            $table->index(['contact_captured_at', 'completed_at'], 'assessments_followup_index');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropIndex('assessments_followup_index');
            $table->dropColumn(['contact_name', 'contact_email', 'contact_phone', 'contact_captured_at']);
        });
    }
};
