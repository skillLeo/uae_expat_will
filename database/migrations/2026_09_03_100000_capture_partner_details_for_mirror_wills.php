<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner details for a mirror-Wills assessment.
 *
 * Agreed with Summit on 28 August 2026: somebody asking for two Wills gives
 * their partner's name, nationality, phone and email on the same screen as
 * their own, and cannot continue without them.
 *
 * These sit on the assessment rather than on the case because they are
 * captured before a case exists — the whole point of taking contact details
 * early is that an assessment abandoned at question nine still leaves Summit
 * somebody to call, and for a mirror pair that means both people.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('partner_name', 120)->nullable()->after('contact_captured_at');
            // Two characters: an ISO country code from the same list as the
            // nationality question, which does not contain the UAE.
            $table->string('partner_nationality', 2)->nullable()->after('partner_name');
            $table->string('partner_phone', 40)->nullable()->after('partner_nationality');
            $table->string('partner_email', 190)->nullable()->after('partner_phone');
        });
    }

    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['partner_name', 'partner_nationality', 'partner_phone', 'partner_email']);
        });
    }
};
