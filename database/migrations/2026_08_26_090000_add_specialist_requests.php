<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The specialist legal review request.
 *
 * Summit's handoff of 26 August is blunt about the problem: choosing "DIFC",
 * "amend an existing Will" or "someone has died" showed a page saying the
 * online Will service is not available. Three of the five things a visitor can
 * ask for were being answered with a rejection screen. Those are enquiries
 * worth money, not people to turn away.
 *
 * These are cases, not a parallel entity. A specialist request needs a
 * reference, an owner, notes, documents, an activity history and a place in the
 * case list — all of which `cases` already does. Building a second table would
 * have meant a second admin screen, a second reference series and a second set
 * of retention rules for the same thing under a different name.
 *
 * `brief_description` is the client's own narrative from the request form. It
 * sits on the case rather than in a note because it is what the client wrote,
 * not what a member of staff observed, and the two should never be confused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            // standard_will | mirror_wills | difc_will | existing_will_service
            // | estate_administration
            $table->string('request_type', 40)->default('standard_will')->after('pathway')->index();
            $table->text('brief_description')->nullable()->after('service_type');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropIndex(['request_type']);
            $table->dropColumn(['request_type', 'brief_description']);
        });
    }
};
